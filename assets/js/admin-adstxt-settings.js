/**
 * @file Handles javascript functionality for Ads TXT admin settings form.
 *
 * @todo: Add translation string support.
 * @todo: Simplify duplicated code.
 */

window.addEventListener('load', () => {

  /**
   * Simple object to help process notices easier.
   */
  let mvAdsTxtNotices = {
    messageBox: document.getElementById('mv_adstxt_notifications'),
    notices: [],
    render: function () {
      let messageBox = this.messageBox;
      messageBox.innerHTML = '';
      messageBox.style.display = 'none';

      let notices = this.notices;
      notices.forEach(function (item, i) {
        let html = '<div class="notice notice-alt notice-' + item.type + '"><p>' + item.message + '</p></div>';
        messageBox.insertAdjacentHTML('beforeend', html);
      });

      messageBox.style.display = 'block';
    },
    setNotices: function (data) {
      this.notices = data;
      this.render();
    }
  }

  /**
   * Handles the click event and messaging for the "Force Recheck Ads.txt Method" button.
   */
  function setupForceRecheck() {
    let btn = document.getElementById('mv_adstxt_recheck_method')
    let isFetching = false

    if (!btn) {
      return;
    }

    // This will listen for keyboard enter/space press events as well.
    btn.addEventListener('click', function () {
      if (isFetching) {
        return
      }

      isFetching = true

      btn.disabled = true;
      btn.classList.add('disabled');

      fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'mv_recheck_adtext',
          _wpnonce: mcpAdsTxtSettings.recheckAdTextNonce
        })
      })
        .then(response => {
          isFetching = false;
          if (!response.ok) {
            throw new Error('Error when trying to recheck ads.txt method');
          }
          response.json().then(data => processRecheckMessage(data));
        })
        .catch(error => {
          // Add generic error message to console.
          console.error('API error:', error);
        });
    })

    /**
     * Process the JSON response after clicking to force recheck.
     */
    function processRecheckMessage(data) {
      let type = 'success';
      let message = 'Ads.txt method has been checked and is now using: ' + data.method;
      if (data.error) {
        type = 'error';
        message = 'Failed to Update: ' + data.error;
      } else {
        updateState(true, data.method);
      }

      mvAdsTxtNotices.setNotices([{
        type: type,
        message: message
      }]);

      let btn = document.getElementById('mv_adstxt_recheck_method');

      btn.disabled = true;
      btn.classList.add('disabled');

      window.setTimeout(function () {
        btn.disabled = false;
        btn.classList.remove('disabled');
      }, 3000)
    }
  }

  /**
   * Handles the click event and messaging for the "Update Ads.txt" button.
   */
  function setupManualUpdate() {
    let btn = document.getElementById('mv_adstxt_sync')
    let isFetching = false

    if (!btn) {
      return;
    }

    // This will listen for keyboard enter/space press events as well.
    btn.addEventListener('click', function () {
      if (isFetching) {
        return
      }

      isFetching = true

      btn.disabled = true;
      btn.classList.add('disabled');

      fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'mv_adtext',
          _wpnonce: mcpAdsTxtSettings.writeAdTextNonce
        })
      })
        .then(response => {
          isFetching = false;
          if (!response.ok) {
            throw new Error('Error when trying to process ads txt');
          }
          response.json().then(data => processUpdatedMessage(data));
        })
        .catch(error => {
          // Add generic error message to console.
          console.error('API error:', error);
        });
    })

    /**
     * Process the JSON response after clicking to manual update.
     */
    function processUpdatedMessage(data) {
      let type = 'success';
      let message = 'Updated!';
      if (data.error) {
        type = 'error';
        message = 'Failed to Update: ' + data.error;
      } else {
        updateState(true, data.method);
      }

      mvAdsTxtNotices.setNotices([{
        type: type,
        message: message
      }]);

      let btn = document.getElementById('mv_adstxt_sync');

      btn.disabled = true;
      btn.classList.add('disabled');

      window.setTimeout(function () {
        btn.disabled = false;
        btn.classList.remove('disabled');
      }, 3000)
    }
  }

  /**
   * Handles the click event and messaging for the "Enable Ads.txt" button.
   */
  function setupEnable() {
    let enableButton = document.getElementById('mv_enable_adstxt');
    if (!enableButton) {
      return;
    }
    let isFetching = false;

    // This will listen for keyboard enter/space press events as well.
    enableButton.addEventListener('click', function () {
      if (isFetching) {
        return
      }
      isFetching = true
      fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'mv_enable_adtext',
          _wpnonce: mcpAdsTxtSettings.enableAdTextNonce
        })
      })
        .then(response => {
          isFetching = false;
          if (!response.ok) {
            throw new Error('Error when trying to process ads txt');
          }
          response.json().then(data => processEnabledMessage(data));
        })
        .catch(error => {
          // Add generic error message to console.
          console.error('API error:', error);
        });
    });

    /**
     * Process the JSON response after clicking to Enable.
     */
    function processEnabledMessage(data) {
      let type = 'success';
      let message = 'You have successfully enabled ads.txt.';
      if (!data.success) {
        type = 'error';
        message = 'Your ads.txt was not enabled, please contact support.';
      }

      mvAdsTxtNotices.setNotices([{
        type: type,
        message: message
      }]);

      if (data.success) {
        updateState(true, data.method);
      }
    }
  }

  /**
   * Handles the click event and messaging for the "Disable Ads.txt" button.
   */
  function setupDisable() {
    let dangerBtn = document.getElementById('mv_disable_adstxt');
    if (!dangerBtn) {
      return;
    }
    let isFetching = false;

    // This will listen for keyboard enter/space press events as well.
    dangerBtn.addEventListener('click', function () {
      if (isFetching) {
        return;
      }

      let confirmed = window.confirm('Are you sure? This will negatively impact ad revenue.')
      if (!confirmed) {
        return;
      }

      isFetching = true;
      fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'mv_disable_adtext',
          _wpnonce: mcpAdsTxtSettings.disableAdTextNonce
        })
      })
        .then(response => {
          isFetching = false;
          if (!response.ok) {
            throw new Error('Error when trying to update ads txt');
          }
          response.json().then(data => processDisabledMessage(data));
        })
        .catch(error => {
          // Add generic error message to console.
          console.error('API error:', error);
        });
    });

    /**
     * Process the JSON response after clicking to disable.
     */
    function processDisabledMessage(data) {
      let type = 'warning';
      let message = 'You have successfully disabled ads.txt, please advise Mediavine Support';
      if (!data.success) {
        type = 'error';
        message = 'Your ads.txt was not disabled, please contact support.';
      }

      mvAdsTxtNotices.setNotices([{
        type: type,
        message: message
      }]);

      if (data.success) {
        updateState(false, data.method);
      }
    }
  }

  /**
   * Toggles visibility of ads.txt config sections based on current config state.
   */
  function updateState (isEnabled, method) {
    let adstxt = document.getElementById('mcp_adstxt');
    if (isEnabled) {
      adstxt.classList.add('mcp-adstxt-enabled');
      adstxt.classList.remove('mcp-adstxt-disabled');
    } else {
      adstxt.classList.add('mcp-adstxt-disabled');
      adstxt.classList.remove('mcp-adstxt-enabled');
    }

    adstxt.classList.remove('mcp-adstxt-method-redirect');
    adstxt.classList.remove('mcp-adstxt-method-write');
    adstxt.classList.remove('mcp-adstxt-method-none');
    adstxt.classList.add('mcp-adstxt-method-' + method);
  }

  setupForceRecheck();
  setupManualUpdate();
  setupEnable();
  setupDisable();
})
