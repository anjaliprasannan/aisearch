/**
 * @file
 * Attaches behaviors for Drupal's active link marking.
 */

(function (Drupal, drupalSettings) {
  /**
   * Append is-active class.
   *
   * The link is only active if its path corresponds to the current path, the
   * language of the linked path is equal to the current language, and if the
   * query parameters of the link equal those of the current request, since the
   * same request with different query parameters may yield a different page
   * (e.g. pagers, exposed View filters).
   *
   * Does not discriminate based on element type, so allows you to set the
   * is-active class on any element: a, li…
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.activeLinks = {
    attach(context) {
      // Start by finding all potentially active links.
      const path = drupalSettings.path;
      const queryString = JSON.stringify(path.currentQuery);
      const querySelector = queryString
        ? `[data-drupal-link-query="${CSS.escape(queryString)}"]`
        : ':not([data-drupal-link-query])';
      const originalSelectors = [
        `[data-drupal-link-system-path="${CSS.escape(path.currentPath)}"]`,
      ];
      let selectors;

      // If this is the front page, we have to check for the <front> path as
      // well.
      if (path.isFront) {
        originalSelectors.push('[data-drupal-link-system-path="<front>"]');
      }

      // Add language filtering.
      selectors = [].concat(
        // Links without any hreflang attributes (most of them).
        originalSelectors.map((selector) => `${selector}:not([hreflang])`),
        // Links with hreflang equals to the current language.
        originalSelectors.map(
          (selector) => `${selector}[hreflang="${path.currentLanguage}"]`,
        ),
      );

      // Add query string selector for pagers, exposed filters.
      selectors = selectors.map((current) => current + querySelector);

      context.querySelectorAll(selectors.join(',')).forEach((activeLink) => {
        activeLink.classList.add('is-active');
        activeLink.setAttribute('aria-current', 'page');
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        context
          .querySelectorAll('[data-drupal-link-system-path].is-active')
          .forEach((activeLink) => {
            activeLink.classList.remove('is-active');
            activeLink.removeAttribute('aria-current');
          });
      }
    },
  };
})(Drupal, drupalSettings);
