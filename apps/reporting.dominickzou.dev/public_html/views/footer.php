    </main>

    <!-- Unified PDF Export Script -->
    <script>
        function exportToPDF() {
            // Hide elements using no-print class temporarily
            const noPrintElements = document.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');
            
            const element = document.body;
            const opt = {
                margin:       0.5,
                filename:     'report.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            // Generate the PDF
            html2pdf().set(opt).from(element).output('blob').then(function(pdfBlob) {
                // Restore no-print elements
                noPrintElements.forEach(el => el.style.display = '');

                // Upload to server to get accessible URL
                let formData = new FormData();
                formData.append("pdf", pdfBlob, "report.pdf");
                
                fetch('/save_pdf.php', { method: "POST", body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.url) {
                        alert("PDF Generated and Saved to Server! Opening URL...");
                        window.location.href = data.url;
                    } else {
                        alert("Error saving PDF.");
                    }
                }).catch(err => {
                    alert("Export system encountered an error.");
                    console.error(err);
                });
            });
        }
    </script>
</body>
</html>
