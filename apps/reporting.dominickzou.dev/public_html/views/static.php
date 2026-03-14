<?php require 'partials/header.php'; ?>
<div class="card">
    <h2>Raw Static Environment Logs</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Time</th><th>Browser</th><th>OS</th><th>Screen Width</th></tr>
        </thead>
        <tbody id="static-body"><tr><td colspan="5">Loading...</td></tr></tbody>
    </table>
</div>
<script src="/assets/js/tables.js"></script>
<script>
    populateTable('/api/static', 'static-body', ['id', 'created_at', 'data.browser', 'data.os', 'data.screen_width']);
</script>
<?php require 'partials/footer.php'; ?>
