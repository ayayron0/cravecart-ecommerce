/*
 * delete-modal.js — handles the delete confirmation modal on admin pages.
 *
 * WHAT: Replaces the browser's default confirm() popup with a styled Bootstrap modal.
 * HOW:  Each delete button carries the item name and delete URL as data attributes
 *       (data-item-name and data-action). When the modal opens, this script reads
 *       those attributes and injects them into the modal — the name into the message
 *       and the URL into the form action. One modal handles every delete button on
 *       the page regardless of whether it's a dish, cuisine, or category.
 * WHERE: Loaded via the extra_js block in menu.twig and add.twig.
 */

// Listen for Bootstrap's pre-show event so the modal is populated before it appears.
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
    // e.relatedTarget is the button that triggered the modal.
    const btn = e.relatedTarget;

    // Inject the item name into the modal message.
    document.getElementById('deleteModalItemName').textContent = btn.dataset.itemName;

    // Set the form action to the correct delete URL for this item.
    document.getElementById('deleteModalForm').action = btn.dataset.action;
});
