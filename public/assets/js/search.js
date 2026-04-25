/*
 * search.js — live search bar for the main navigation.
 *
 * WHAT: Listens for keystrokes in the search input and fetches matching
 *       dishes from the server without reloading the page (AJAX).
 * HOW:  On each keystroke it waits 300ms (debounce) before sending a
 *       GET request to /search?q=... . The server returns a JSON array
 *       of dishes which are rendered as a dropdown below the search bar.
 *       Clicking a result navigates to that dish's browse page.
 *       Results are sanitized with escHtml() before being injected into
 *       the DOM to prevent XSS attacks.
 * WHERE: Loaded globally via layout.twig so it runs on every page.
 */

// Grab the search input and the empty results container from the DOM.
// Both elements are defined in common/header.twig.
const searchInput   = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');

if (searchInput) {
    // Read the API URL from the data attribute on the input so this script
    // works regardless of the app's base path (e.g. /cravecart or /).
    const searchUrl = searchInput.dataset.searchUrl;

    // debounceTimer holds the ID of the pending setTimeout so we can cancel
    // it if the user types again before it fires.
    let debounceTimer;

    // Listen for every keystroke in the search input.
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();

        // Cancel any fetch that was queued from the previous keystroke.
        clearTimeout(debounceTimer);

        // Don't search for very short strings — avoids hitting the server on
        // single characters and matches the 2-character minimum in the controller.
        if (query.length < 2) {
            hideResults();
            return;
        }

        // Wait 300 ms after the user stops typing before sending the request.
        // This prevents a new fetch on every single keypress.
        debounceTimer = setTimeout(() => {
            // encodeURIComponent makes the query string URL-safe (e.g. spaces → %20).
            fetch(searchUrl + '?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(dishes => renderResults(dishes))
                .catch(() => hideResults()); // silently hide the dropdown on network errors
        }, 300);
    });

    // Close the dropdown when the user clicks anywhere outside the search wrapper.
    document.addEventListener('click', (e) => {
        if (!document.getElementById('search-wrapper').contains(e.target)) {
            hideResults();
        }
    });
}

// Builds and injects the results dropdown from the array of dish objects
// returned by the /search endpoint.
function renderResults(dishes) {
    if (!dishes.length) {
        searchResults.innerHTML = '<p style="padding:16px 20px; margin:0; color:#6b7280; font-size:.875rem;">No results found.</p>';
        searchResults.style.display = 'block';
        return;
    }

    // Strip the '/search' suffix to get the app base URL, then build a
    // browse link for each dish using its cuisine slug and category name.
    const base = searchInput.dataset.searchUrl.replace('/search', '');

    const html = dishes.map(dish => {
        // The browse URL pattern is /browse/{category}/{cuisine_slug}.
        // encodeURIComponent keeps the URL valid if names contain special characters.
        const category = encodeURIComponent(dish.category_name.toLowerCase());
        const cuisine  = encodeURIComponent(dish.cuisine_slug.toLowerCase());
        const href     = `${base}/browse/${category}/${cuisine}`;

        // escHtml() sanitizes the dish name and cuisine name before inserting
        // them into innerHTML, preventing XSS if a dish name contains HTML characters.
        return `
        <a href="${href}" style="display:flex; align-items:center; justify-content:space-between;
            padding:12px 20px; text-decoration:none; color:#111827; border-bottom:1px solid #f3f4f6;
            font-size:.875rem; transition:background .15s;"
            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''"
        >
            <div>
                <div style="font-weight:600;">${escHtml(dish.name)}</div>
                <div style="color:#6b7280; font-size:.8rem;">${escHtml(dish.cuisine_name)} · ${escHtml(dish.category_name)}</div>
            </div>
            <span style="font-weight:700; color:#e63946;">$${parseFloat(dish.price).toFixed(2)}</span>
        </a>`;
    }).join('');

    searchResults.innerHTML = html;
    searchResults.style.display = 'block';
}

// Hides the dropdown and clears its content.
function hideResults() {
    searchResults.style.display = 'none';
    searchResults.innerHTML = '';
}

// Escapes HTML special characters before inserting user-supplied strings into
// innerHTML. Prevents injected tags or scripts from being parsed by the browser.
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
