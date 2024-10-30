/**
 * @file Handles javascript functionality for Launch Mode admin settings.
 */

window.addEventListener('load', () => {
  function setupDisableLaunchMode() {
    let button = document.getElementById('mv_disable_launch_mode');
    if (!button) {
      return;
    }
    button.addEventListener('click', () => {
      let confirmed = window.confirm('You are about to exit launch mode. Please only do this if instructed to by a Mediavine Support team member.');
      if (confirmed) {
        fetch(ajaxurl, {
          method: 'POST',
          body: new URLSearchParams({
            action: 'mv_disable_launch_mode',
            _wpnonce: mcpLaunchSettings.disableLaunchModeNonce
          })
        })
          .then(response => {
            if (!response.ok) {
              throw new Error('API error');
            }
            // Remove buttons on a successful operation.
            document.getElementById('mv_control_launch_mode').innerHTML = '';
          })
          .catch(error => {
            // Add generic error message to console.
            console.error('API error:', error);
          });
      }
    });
  }

  function setupRefreshLaunchMode() {
    let button = document.getElementById('mv_refresh_launch_mode');
    let isRefreshing = false;
    if (!button) {
      return;
    }

    button.addEventListener('click', () => {
      if (isRefreshing) {
        return;
      }

      isRefreshing = true;
      button.disabled = true;
      button.classList.add('disabled');

      fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'mv_refresh_launch_mode',
          _wpnonce: mcpLaunchSettings.refreshLaunchModeNonce
        })
      })
        .then(response => {
          isRefreshing = false;
          button.disabled = false;
          button.classList.remove('disabled');

          if (!response.ok) {
            throw new Error('API error');
          }

          response.json().then(data => {
            if (!data.data) {
              return;
            }

            if (data.data.launch_mode === false) {
              location.reload();
            }
          });
        })
        .catch(error => {
          // Add generic error message to console.
          console.error('API error:', error);
        });
    })
  }

  setupDisableLaunchMode();
  setupRefreshLaunchMode();
})
