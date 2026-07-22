/**
 * assets/js/search.js - Salon Pro Search Functionality
 * Handles search panel, real-time results, keyboard shortcuts, and recent searches
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ============================================
    // DOM REFERENCES
    // ============================================
    const searchToggle = document.getElementById('searchToggle');
    const searchOverlay = document.getElementById('searchOverlay');
    const searchPanel = document.getElementById('searchPanel');
    const searchInput = document.getElementById('searchInput');
    const searchTabs = document.querySelectorAll('.search-tab');
    const searchResults = document.getElementById('searchResults');
    const searchRecent = document.getElementById('searchRecent');
    const searchClose = document.getElementById('searchClose');
    const searchClear = document.getElementById('searchClear');
    const resultsCount = document.getElementById('resultsCount');
    const loadingIndicator = document.getElementById('searchLoading');

    // ============================================
    // STATE
    // ============================================
    let currentCategory = 'all';
    let searchTimeout = null;
    let isOpen = false;
    let selectedIndex = -1;
    let resultsData = [];

    // ============================================
    // OPEN / CLOSE SEARCH
    // ============================================
    function openSearch() {
        if (isOpen) return;
        isOpen = true;
        searchOverlay.classList.add('active');
        searchPanel.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(function() {
            searchInput.focus();
        }, 200);
        loadRecentSearches();
    }

    function closeSearch() {
        if (!isOpen) return;
        isOpen = false;
        searchOverlay.classList.remove('active');
        searchPanel.classList.remove('active');
        document.body.style.overflow = '';
        searchInput.value = '';
        searchResults.innerHTML = '';
        searchResults.style.display = 'none';
        resultsCount.textContent = '';
        selectedIndex = -1;
    }

    // ============================================
    // LOAD RECENT SEARCHES
    // ============================================
    function loadRecentSearches() {
        fetch('../super_admin/api/search.php?action=recent')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.searches && data.searches.length > 0) {
                    renderRecentSearches(data.searches);
                } else {
                    searchRecent.innerHTML = '<div class="search-recent-empty">No recent searches</div>';
                }
            })
            .catch(function() {
                searchRecent.innerHTML = '<div class="search-recent-empty">Unable to load recent searches</div>';
            });
    }

    function renderRecentSearches(searches) {
        let html = '';
        searches.forEach(function(search) {
            const timeAgo = timeElapsed(search.created_at);
            html += `
                <div class="search-recent-item" data-query="${search.query}" data-category="${search.category}">
                    <span class="search-recent-icon">🔄</span>
                    <span class="search-recent-query">${escapeHtml(search.query)}</span>
                    <span class="search-recent-time">${timeAgo}</span>
                    <button class="search-recent-remove" data-id="${search.id}" title="Remove">✕</button>
                </div>
            `;
        });
        html += `
            <div class="search-recent-footer">
                <button id="clearRecentSearches" class="search-recent-clear">Clear All</button>
            </div>
        `;
        searchRecent.innerHTML = html;

        // Add event listeners to recent items
        document.querySelectorAll('.search-recent-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.search-recent-remove')) return;
                const query = this.dataset.query;
                const category = this.dataset.category;
                searchInput.value = query;
                currentCategory = category;
                updateTabs(category);
                performSearch(query, category);
            });
        });

        // Remove individual recent search
        document.querySelectorAll('.search-recent-remove').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.dataset.id;
                removeRecentSearch(id);
            });
        });

        // Clear all recent searches
        const clearBtn = document.getElementById('clearRecentSearches');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (confirm('Clear all recent searches?')) {
                    clearAllRecentSearches();
                }
            });
        }
    }

    function removeRecentSearch(id) {
        fetch('../super_admin/api/search.php?action=remove_recent&id=' + id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    loadRecentSearches();
                }
            });
    }

    function clearAllRecentSearches() {
        fetch('../super_admin/api/search.php?action=clear_recent')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    loadRecentSearches();
                }
            });
    }

    // ============================================
    // PERFORM SEARCH (Real-time)
    // ============================================
    function performSearch(query, category, saveToHistory = false) {
    if (query.length < 2) {
        searchResults.style.display = 'none';
        resultsCount.textContent = '';
        return;
    }

    // Show loading
    loadingIndicator.style.display = 'block';
    searchResults.style.display = 'none';

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        fetch('../super_admin/api/search.php?q=' + encodeURIComponent(query) + '&category=' + category)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                loadingIndicator.style.display = 'none';
                if (data.success && data.results && data.results.length > 0) {
                    renderResults(data.results, data.total);
                    // Only save to history if explicitly requested (Enter key)
                    if (saveToHistory) {
                        // The API already saves the search, so we don't need to do anything here
                        // But we should reload recent searches
                        loadRecentSearches();
                    }
                } else {
                    searchResults.style.display = 'block';
                    searchResults.innerHTML = `
                        <div class="search-no-results">
                            <span class="search-no-results-icon">🔍</span>
                            <p>No results found for <strong>"${escapeHtml(query)}"</strong></p>
                            <p style="font-size: 0.8rem; color: #7a7568;">Try a different search term or category</p>
                        </div>
                    `;
                    resultsCount.textContent = '0 results';
                }
            })
            .catch(function() {
                loadingIndicator.style.display = 'none';
                searchResults.style.display = 'block';
                searchResults.innerHTML = `
                    <div class="search-no-results">
                        <span class="search-no-results-icon">⚠️</span>
                        <p>Unable to perform search. Please try again.</p>
                    </div>
                `;
            });
    }, 300);
}

        // Show loading


    // ============================================
    // RENDER RESULTS
    // ============================================
    function renderResults(results, total) {
        let html = '';
        html += `
            <div class="search-results-header">
                <span>${total} results found</span>
            </div>
            <table class="search-results-table">
                <thead>
                    <tr>
                        <th>Result</th>
                        <th>Type</th>
                        <th>Salon</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
        `;

        results.forEach(function(item) {
            const statusClass = item.status === 'Active' || item.status === 'active' ? 'status-active' : 'status-inactive';
            const icon = item.icon || getTypeIcon(item.type);
            html += `
                <tr>
                    <td><span class="search-result-icon">${icon}</span> ${escapeHtml(item.name)}</td>
                    <td><span class="search-result-type">${escapeHtml(item.type)}</span></td>
                    <td>${escapeHtml(item.salon_name || '-')}</td>
                    <td><span class="search-result-status ${statusClass}">${escapeHtml(item.status)}</span></td>
                    <td><a href="${escapeHtml(item.url)}" class="search-result-view">👁️ View</a></td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        searchResults.innerHTML = html;
        searchResults.style.display = 'block';
        resultsCount.textContent = total + ' results';
        resultsData = results;
    }

    // ============================================
    // UPDATE TABS
    // ============================================
    function updateTabs(category) {
        searchTabs.forEach(function(tab) {
            const tabCategory = tab.dataset.category;
            if (tabCategory === category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================

    // Toggle search on icon click
    if (searchToggle) {
        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (isOpen) {
                closeSearch();
            } else {
                openSearch();
            }
        });
    }

    // Close search
    if (searchClose) {
        searchClose.addEventListener('click', closeSearch);
    }

    // Close on overlay click
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSearch();
            }
        });
    }

    // Search input - real-time (but don't save to history)
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length > 0) {
            searchRecent.style.display = 'none';
            performSearch(query, currentCategory, false); // Don't save to history
        } else {
            searchResults.style.display = 'none';
            resultsCount.textContent = '';
            searchRecent.style.display = 'block';
            loadRecentSearches();
        }
    });

    // Enter key - save to history
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query.length > 0) {
                performSearch(query, currentCategory, true); // Save to history
            }
        }
    });
}
    // Category tabs - save to history when clicked
searchTabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
        const category = this.dataset.category;
        currentCategory = category;
        updateTabs(category);
        const query = searchInput.value.trim();
        if (query.length > 0) {
            performSearch(query, category, true); // Save to history
        }
    });
});

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K or / to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (isOpen) {
                closeSearch();
            } else {
                openSearch();
            }
            return;
        }

        // / key to open search (when not in input)
        if (e.key === '/' && !isOpen && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
            e.preventDefault();
            openSearch();
            return;
        }

        // ESC to close
        if (e.key === 'Escape' && isOpen) {
            closeSearch();
            return;
        }

        // Arrow navigation in results
        if (isOpen && searchResults.style.display !== 'none') {
            const items = searchResults.querySelectorAll('tbody tr');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                highlightResult(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                highlightResult(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const link = items[selectedIndex].querySelector('.search-result-view');
                if (link) {
                    window.location.href = link.href;
                }
            }
        }
    });

    function highlightResult(items) {
        items.forEach(function(item, index) {
            if (index === selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getTypeIcon(type) {
        const icons = {
            'salon': '🏪',
            'owner': '👤',
            'staff': '👥'
        };
        return icons[type] || '📌';
    }

    function timeElapsed(datetime) {
        if (!datetime) return 'Just now';
        const now = new Date();
        const then = new Date(datetime);
        const diff = now - then;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' min ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
        if (diff < 2592000000) return Math.floor(diff / 86400000) + ' days ago';
        if (diff < 31536000000) return Math.floor(diff / 2592000000) + ' months ago';
        return Math.floor(diff / 31536000000) + ' years ago';
    }

    // ============================================
    // KEYBOARD SHORTCUT HELPER
    // ============================================
    function showKeyboardShortcut() {
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        return isMac ? '⌘K' : 'Ctrl+K';
    }

    // Update the placeholder to show keyboard shortcut
    if (searchInput) {
        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        const shortcut = isMac ? '⌘K' : 'Ctrl+K';
        searchInput.placeholder = 'Search salons, owners, or staff... (' + shortcut + ')';
    }

    // ============================================
    // CLOSE SEARCH ON RESIZE (Mobile)
    // ============================================
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // Close search on mobile orientation change
            if (window.innerWidth < 768 && isOpen) {
                // Keep open on mobile, just adjust layout
            }
        }, 300);
    });

    console.log('🔍 Search functionality loaded. Press Ctrl+K or / to search.');
});