<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics API Documentation</title>
    <link rel="stylesheet" href="/assets/swagger/swagger-ui.css" />
    <style>
        body { margin: 0; padding: 0; background-color: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="/assets/swagger/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/assets/swagger/swagger.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ]
            });
        };
    </script>
</body>
</html>
