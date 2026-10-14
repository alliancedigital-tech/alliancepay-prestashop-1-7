/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 *
 * Shows the plan list only while "Subscribe" is chosen on the product page.
 */
(function () {
  'use strict';

  function refresh() {
    var blocks = document.querySelectorAll('.alliance-subscription-choice');

    Array.prototype.forEach.call(blocks, function (block) {
      var checked = block.querySelector('.alliance-purchase-type-input:checked');
      var plans = block.querySelector('.alliance-plans');

      if (plans) {
        plans.style.display = checked && checked.value === 'subscription' ? 'block' : 'none';
      }
    });
  }

  document.addEventListener('change', function (event) {
    if (event.target && event.target.classList && event.target.classList.contains('alliance-purchase-type-input')) {
      refresh();
    }
  });

  if (window.prestashop && typeof window.prestashop.on === 'function') {
    // The product block is re-rendered on combination change: state goes back to one-time.
    window.prestashop.on('updatedProduct', refresh);
  }

  document.addEventListener('DOMContentLoaded', refresh);
})();
