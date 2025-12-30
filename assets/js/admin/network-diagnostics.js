(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var runButton = document.getElementById('run-diagnostics');
        if (!runButton) {
            return;
        }

        runButton.addEventListener('click', function() {
            var button = this;
            var resultsDiv = document.getElementById('truebeep-diagnostics-results');
            
            button.disabled = true;
            button.textContent = 'Running...';
            
            resultsDiv.innerHTML = '<div class="notice notice-info"><p>Running diagnostics...</p></div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=truebeep_test_github_connection&_wpnonce=' + truebeepDiagnostics.nonce
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                resultsDiv.innerHTML = data.data.html;
                button.disabled = false;
                button.textContent = 'Run Diagnostics';
            })
            .catch(function(error) {
                resultsDiv.innerHTML = '<div class="notice notice-error"><p>Error running diagnostics: ' + error.message + '</p></div>';
                button.disabled = false;
                button.textContent = 'Run Diagnostics';
            });
        });
    });
})();

