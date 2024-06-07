document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('rui-bulk-submit-form').addEventListener('submit', function(e) {
        e.preventDefault();

        var formData = new FormData();
        formData.append('action', 'rui_bulk_submit');
        formData.append('nonce', rui_ajax.nonce);
        formData.append('project_name', document.getElementById('rui-project-name').value);
        formData.append('urls', document.getElementById('rui-urls').value);

        fetch(rui_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('rui-bulk-submit-response').innerHTML = '<div class="notice notice-success"><p>' + data.data + '</p></div>';
            } else {
                document.getElementById('rui-bulk-submit-response').innerHTML = '<div class="notice notice-error"><p>' + data.data + '</p></div>';
            }
        })
        .catch(error => {
            document.getElementById('rui-bulk-submit-response').innerHTML = '<div class="notice notice-error"><p>Error: ' + error + '</p></div>';
        });
    });
});
