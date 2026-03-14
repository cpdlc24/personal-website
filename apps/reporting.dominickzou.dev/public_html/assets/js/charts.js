// Helper to extract nested data ('data.loadTime' from the row object)
function getNestedValue(obj, path) {
    return path.split('.').reduce((acc, part) => acc && acc[part], obj);
}

/**
 * 1. Trend Chart (Line Chart over time)
 * Best for: Performance Data (e.g., Load Times over time)
 */
async function renderTrendChart(endpoint, canvasId, valueKey, label = 'Metric', onClickUrl = null) {
    try {
        const response = await fetch(endpoint);
        const rawData = await response.json();

        // Reverse so the chart flows chronologically left-to-right
        const chronologicalData = rawData.reverse();

        // The DB returns eg "2026-03-09 05:49:21" (UTC). We append 'Z' and 'T' so Javascript parses it correctly.
        const labels = chronologicalData.map(row => {
            const d = new Date(row.created_at.replace(' ', 'T') + 'Z');
            return d.toLocaleDateString(undefined, { month: 'numeric', day: 'numeric' }) + ' ' + d.toLocaleTimeString();
        });
        const dataPoints = chronologicalData.map(row => getNestedValue(row, valueKey) || 0);

        new Chart(document.getElementById(canvasId), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: dataPoints,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (e, elements, chart) => {
                    if (onClickUrl && elements && elements.length > 0) {
                        const idx = elements[0].index;
                        const clickedLabel = chart.data.labels[idx];
                        window.location.href = `${onClickUrl}?filter=${encodeURIComponent(clickedLabel)}`;
                    } else if (onClickUrl) {
                        window.location.href = onClickUrl;
                    }
                }
            }
        });
    } catch (error) {
        console.error("Trend Chart Error:", error);
    }
}

/**
 * 1.5 Grouped Trend Chart (Line Charts over time separated by a grouping key, plus an overall average)
 */
async function renderGroupedTrendChart(endpoint, containerId, groupKey, valueKey, label = 'Metric', onClickUrl = null) {
    try {
        const response = await fetch(endpoint);
        const rawData = await response.json();
        const container = document.getElementById(containerId);
        if (!container) return;

        // Clear existing charts if any for dynamic re-rendering
        container.innerHTML = '';

        // Group data by groupKey (e.g., page_url)
        const groups = {};
        const chronologicalData = rawData.reverse();

        chronologicalData.forEach(row => {
            const grp = getNestedValue(row, groupKey) || 'Unknown';
            if (!groups[grp]) groups[grp] = [];

            const d = new Date(row.created_at.replace(' ', 'T') + 'Z');
            const timeLabel = d.toLocaleDateString(undefined, { month: 'numeric', day: 'numeric' }) + ' ' + d.toLocaleTimeString();

            groups[grp].push({
                timeLabel: timeLabel,
                val: getNestedValue(row, valueKey) || 0
            });
        });

        // Compute overall average per time slot (simplified: just plotting all points sorted)
        const allPoints = chronologicalData.map((row, index) => {
            const d = new Date(row.created_at.replace(' ', 'T') + 'Z');
            const timeLabel = d.toLocaleDateString(undefined, { month: 'numeric', day: 'numeric' }) + ' ' + d.toLocaleTimeString();
            return {
                timeLabel: timeLabel,
                val: getNestedValue(row, valueKey) || 0,
                index: index
            };
        });

        // Moving average or just sequential plot for the "Overall" chart
        // Let's just create an "Overall" chart that plots all chronological events
        const downsample = (arr, max) => {
            if (arr.length <= max) return arr;
            const step = (arr.length - 1) / (max - 1);
            const result = [];
            for (let i = 0; i < max; i++) {
                result.push(arr[Math.round(i * step)]);
            }
            return result;
        };

        const MAX_POINTS_OVERALL = 60;
        const downsampledOverall = downsample(allPoints, MAX_POINTS_OVERALL);
        const overallLabels = downsampledOverall.map(p => p.timeLabel);
        const overallData = downsampledOverall.map(p => p.val);

        // Helper function to create canvas
        const createChartCanvas = (title, isOverall) => {
            const card = document.createElement('div');
            card.className = 'card';
            // Custom class for full width vs normal width
            if (isOverall) {
                card.classList.add('full-width', 'clickable-chart');
            } else {
                card.classList.add('clickable-chart');
            }

            if (onClickUrl) {
                card.onclick = () => {
                    if (isOverall) window.location.href = onClickUrl;
                    else window.location.href = `${onClickUrl}?filter=${encodeURIComponent(title)}`;
                };
            }

            const heading = document.createElement('h3');
            heading.textContent = title;
            card.appendChild(heading);

            const chartDiv = document.createElement('div');
            chartDiv.className = 'chart-container';
            const canvas = document.createElement('canvas');
            chartDiv.appendChild(canvas);
            card.appendChild(chartDiv);

            container.appendChild(card);
            return canvas;
        };

        // Render Overall Average
        new Chart(createChartCanvas('Overall Load Times', true), {
            type: 'line',
            data: {
                labels: overallLabels,
                datasets: [{
                    label: label + ' (All Sites)',
                    data: overallData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { display: true } }
                }
            }
        });

        const MAX_POINTS_SITE = 20;

        // Render Individual Groups
        Object.keys(groups).forEach(site => {
            const downsampledSite = downsample(groups[site], MAX_POINTS_SITE);
            const siteLabels = downsampledSite.map(p => p.timeLabel);
            const siteData = downsampledSite.map(p => p.val);

            let siteTitle = site;
            try {
                // Formatting just to ensure it's a clean string, but keep the full href/path
                siteTitle = new URL(site).href.replace(/^https?:\/\//, '');
            } catch (e) { }

            new Chart(createChartCanvas(siteTitle, false), {
                type: 'line',
                data: {
                    labels: siteLabels,
                    datasets: [{
                        label: label,
                        data: siteData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { display: false } }
                    }
                }
            });
        });

    } catch (error) {
        console.error("Grouped Trend Chart Error:", error);
    }
}

/**
 * 2. Distribution Chart (Bar/Pie Chart of categorical counts)
 * Best for: Activity (Action types) or Static (Browser types)
 */
async function renderDistributionChart(endpoint, canvasId, type, categoryKey, label = 'Count', onClickUrl = null) {
    try {
        const response = await fetch(endpoint);
        const rawData = await response.json();

        // Count occurrences of the category (e.g., {"click": 45, "scroll": 12})
        const counts = {};
        rawData.forEach(row => {
            const category = getNestedValue(row, categoryKey) || 'Unknown';
            counts[category] = (counts[category] || 0) + 1;
        });

        const bgColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

        new Chart(document.getElementById(canvasId), {
            type: type, // 'bar', 'pie', or 'doughnut'
            data: {
                labels: Object.keys(counts),
                datasets: [{
                    label: label,
                    data: Object.values(counts),
                    backgroundColor: bgColors.slice(0, Object.keys(counts).length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                onClick: (e, elements, chart) => {
                    if (onClickUrl && elements && elements.length > 0) {
                        const idx = elements[0].index;
                        const clickedLabel = chart.data.labels[idx];
                        window.location.href = `${onClickUrl}?filter=${encodeURIComponent(clickedLabel)}`;
                    } else if (onClickUrl) {
                        window.location.href = onClickUrl;
                    }
                }
            }
        });
    } catch (error) {
        console.error("Distribution Chart Error:", error);
    }
}

/**
 * 2.5 Grouped Distribution Chart (Bar/Pie Charts grouped by a key, plus overall)
 */
async function renderGroupedDistributionChart(endpoint, containerId, groupKey, categoryKey, type = 'bar', label = 'Count', onClickUrl = null) {
    try {
        const response = await fetch(endpoint);
        const rawData = await response.json();
        const container = document.getElementById(containerId);
        if (!container) return;

        // Clear existing charts if any for dynamic re-rendering
        container.innerHTML = '';

        const groups = {};
        const overallCounts = {};

        rawData.forEach(row => {
            const grp = getNestedValue(row, groupKey) || 'Unknown';
            const category = getNestedValue(row, categoryKey) || 'Unknown';

            if (!groups[grp]) groups[grp] = {};
            groups[grp][category] = (groups[grp][category] || 0) + 1;

            overallCounts[category] = (overallCounts[category] || 0) + 1;
        });

        const bgColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6366f1', '#ec4899', '#14b8a6'];

        // Ensure consistent colors for each category name across all charts
        const categoryColorMap = {};
        let colorIdx = 0;
        const getColorForCategory = (cat) => {
            if (!categoryColorMap[cat]) {
                categoryColorMap[cat] = bgColors[colorIdx % bgColors.length];
                colorIdx++;
            }
            return categoryColorMap[cat];
        };

        // Pre-map all overall categories so they consistently lock in colors
        const overallLabelsKeys = Object.keys(overallCounts);
        overallLabelsKeys.forEach(getColorForCategory);

        const createChartCanvas = (title, isFullWidth = false) => {
            const card = document.createElement('div');
            card.className = 'card clickable-card' + (isFullWidth ? ' full-width' : '');
            if (onClickUrl) {
                card.onclick = () => window.location.href = onClickUrl;
            }
            const heading = document.createElement('h3');
            heading.textContent = title;
            card.appendChild(heading);

            const chartDiv = document.createElement('div');
            chartDiv.className = 'chart-container';
            const canvas = document.createElement('canvas');
            chartDiv.appendChild(canvas);
            card.appendChild(chartDiv);

            container.appendChild(card);
            return canvas;
        };

        // Render Overall Average
        new Chart(createChartCanvas('Overall ' + label + 's', true), {
            type: type,
            data: {
                labels: overallLabelsKeys,
                datasets: [{
                    label: label + ' (All Sites)',
                    data: Object.values(overallCounts),
                    backgroundColor: overallLabelsKeys.map(getColorForCategory),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { display: true } }
                }
            }
        });

        // Render Individual Groups
        Object.keys(groups).forEach(site => {
            const counts = groups[site];
            const siteKeys = Object.keys(counts);

            let siteTitle = site;
            try { siteTitle = new URL(site).href.replace(/^https?:\/\//, ''); } catch (e) { }

            new Chart(createChartCanvas(siteTitle, false), {
                type: type,
                data: {
                    labels: siteKeys,
                    datasets: [{
                        label: label,
                        data: Object.values(counts),
                        backgroundColor: siteKeys.map(getColorForCategory),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { display: false } }
                    }
                }
            });
        });

    } catch (error) {
        console.error("Grouped Distribution Chart Error:", error);
    }
}
