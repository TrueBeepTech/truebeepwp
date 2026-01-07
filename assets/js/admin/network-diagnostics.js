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
            button.textContent = truebeep_smwl_diagnostics.strings.running;
            
            resultsDiv.innerHTML = '<div class="notice notice-info"><p>' + truebeep_smwl_diagnostics.strings.runningDiagnostics + '</p></div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=truebeep_smwl_test_network_connection&_wpnonce=' + truebeep_smwl_diagnostics.nonce
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                resultsDiv.innerHTML = data.data.html;
                button.disabled = false;
                button.textContent = truebeep_smwl_diagnostics.strings.runDiagnostics;
            })
            .catch(function(error) {
                var errorMessage = truebeep_smwl_diagnostics.strings.errorRunning.replace('%s', error.message);
                resultsDiv.innerHTML = '<div class="notice notice-error"><p>' + errorMessage + '</p></div>';
                button.disabled = false;
                button.textContent = truebeep_smwl_diagnostics.strings.runDiagnostics;
            });
        });
    });
})();

