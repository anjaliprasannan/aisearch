'use strict';

(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.aiTranslateTextfieldAction = {
    attach: function (context, settings) {
      const buttons = once('ai-translation-modal-warning', context.querySelectorAll('.ai-translator-warning-button'));
      buttons.forEach(function (button) {
        button.addEventListener('click', openWarningModal);
      });

      function openWarningModal(event) {
        event.preventDefault();
        const translatorWarningDialog = document.createElement('div');
        translatorWarningDialog.innerHTML = settings.ai_translate_textfield_modal.dialog_content;

        const id = event.target.getAttribute('data-ai-translator-id');
        const targetButton = context.querySelector('[name="translate_button-' + id + '"]');

        const dialog = Drupal.dialog(translatorWarningDialog, {
          title: settings.ai_translate_textfield_modal.dialog_title,
          width: '50%',
          buttons: [
            {
              text: settings.ai_translate_textfield_modal.dialog_ok_button,
              class: 'button--primary',
              click: function () {
                if (targetButton) {
                  event.target.classList.add('js-hide', 'hidden');
                  targetButton.classList.remove('js-hide', 'hidden');
                  targetButton.dispatchEvent(new Event('click', {bubbles: true}));
                }
                dialog.close();
              },
            },
            {
              text: settings.ai_translate_textfield_modal.dialog_cancel_button,
              class: 'button--secondary',
              click: function () {
                dialog.close();
              },
            },
          ],
        });

        // Show the dialog.
        dialog.showModal();
      }
    },
  };
})(Drupal, drupalSettings, once);
