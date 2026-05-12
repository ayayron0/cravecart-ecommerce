/*
 * cancel-modal.js — handles the cancel order confirmation modal on the admin orders page.
 *
 * WHAT: Replaces the browser confirm() popup with a styled Bootstrap modal.
 * HOW:  Each cancel button carries the order label and cancel URL as data attributes.
 *       When the modal opens, this script reads those attributes and injects them
 *       into the modal message and form action so one modal handles every order.
 * WHERE: Loaded via the extra_js block in Admin/orders.twig.
 */

document.getElementById('cancelModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;

    // Inject the order label (e.g. "Order #5") into the modal message.
    document.getElementById('cancelModalItemName').textContent = btn.dataset.itemName;

    // Point the form to the correct cancel URL for this order.
    document.getElementById('cancelModalForm').action = btn.dataset.action;
});
