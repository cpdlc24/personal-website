async function populateTable(endpoint, tableBodyId, columns) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;

    try {
        const response = await fetch(endpoint);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const rows = await response.json();

        tbody.innerHTML = ''; // Clear loading state

        // Parse current URL for ?filter= keyword
        const urlParams = new URLSearchParams(window.location.search);
        const filterKeyword = urlParams.get('filter') ? urlParams.get('filter').toLowerCase() : null;

        const matchRows = [];
        const nonMatchRows = [];

        rows.forEach(row => {
            const tr = document.createElement('tr');
            let rowMatchesFilter = false;

            columns.forEach(col => {
                const td = document.createElement('td');

                // Path resolver for nested JSON (e.g., 'data.loadTime')
                let value = row;
                const path = col.split('.');
                for (let i = 0; i < path.length; i++) {
                    value = value && value[path[i]] !== undefined ? value[path[i]] : null;
                }

                // Format dates nicely if it's the created_at column
                if (col === 'created_at' && value && typeof value === 'string') {
                    // Strict ISO8601 parsing Requires T to split Date and Time
                    value = new Date(value.replace(' ', 'T') + 'Z').toLocaleString();
                }

                td.textContent = value || 'N/A';
                tr.appendChild(td);

                // If filter is active, check if this text content matches it in any column
                if (filterKeyword && td.textContent.toLowerCase().includes(filterKeyword)) {
                    rowMatchesFilter = true;
                }
            });

            if (filterKeyword && rowMatchesFilter) {
                tr.style.backgroundColor = 'rgba(59, 130, 246, 0.05)'; // Light blue highlight
                matchRows.push(tr);
            } else {
                nonMatchRows.push(tr);
            }
        });

        matchRows.forEach(tr => tbody.appendChild(tr));
        nonMatchRows.forEach(tr => tbody.appendChild(tr));

    } catch (error) {
        console.error(`Error loading table from ${endpoint}:`, error);
        tbody.innerHTML = `<tr><td colspan="${columns.length}" style="color:red; text-align:center;">Failed to load data.</td></tr>`;
    }
}
