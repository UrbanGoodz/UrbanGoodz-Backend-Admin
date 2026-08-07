{{-- Urban Goodz Command Center — Global Command Palette Overlay (Cmd+K / Ctrl+K) --}}
<div id="ug-command-palette-modal" class="ug-command-palette-backdrop d-none" tabindex="-1">
    <div class="ug-command-palette-card">
        <!-- Search Header Input -->
        <div class="ug-command-palette-input-wrap">
            <i class="tio-search text-primary" style="font-size: 20px;"></i>
            <input type="text" id="ug-command-search-input" class="ug-command-palette-input" placeholder="{{ translate('Search orders, drivers, stores, dispatches, or ask Skylar...') }}" autocomplete="off" autofocus>
            <span class="ug-shortcut-badge">ESC</span>
        </div>

        <!-- Quick Suggestions & Navigation Results -->
        <div class="ug-command-palette-results" id="ug-command-results-list">
            <!-- Skylar AI Copilot Actions -->
            <div class="px-2 py-1 text-muted text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.8px;">
                <i class="tio-flash mr-1 text-warning"></i> {{ translate('Skylar AI Quick Actions') }}
            </div>
            
            <a href="{{ route('admin.urban-goodz.ai-chief-of-staff') }}" class="ug-command-item active">
                <div class="d-flex align-items-center">
                    <i class="tio-user-big"></i>
                    <div>
                        <div class="font-weight-bold">{{ translate('Executive Operations Briefing') }}</div>
                        <small class="text-muted">{{ translate('Ask Skylar for daily revenue, dispatch risk alerts & pending tasks') }}</small>
                    </div>
                </div>
                <span class="badge badge-soft-warning">P0 Priority</span>
            </a>

            <a href="{{ route('admin.urban-goodz.ai-operations.workforce.approvals') }}" class="ug-command-item">
                <div class="d-flex align-items-center">
                    <i class="tio-checkmark-circle-outlined"></i>
                    <div>
                        <div class="font-weight-bold">{{ translate('Review Supervised Approvals Queue') }}</div>
                        <small class="text-muted">{{ translate('Approve pending driver payouts, withdrawals, and store updates') }}</small>
                    </div>
                </div>
                <span class="ug-shortcut-badge">AI Action</span>
            </a>

            <!-- Quick Navigation Sections -->
            <div class="px-2 py-1 text-muted text-uppercase font-weight-bold mt-2" style="font-size: 11px; letter-spacing: 0.8px;">
                <i class="tio-compass mr-1 text-primary"></i> {{ translate('Command Center Navigation') }}
            </div>

            <a href="{{ route('admin.dashboard') }}" class="ug-command-item">
                <div class="d-flex align-items-center">
                    <i class="tio-dashboard-outlined"></i>
                    <span>{{ translate('Main Operations Dashboard') }}</span>
                </div>
                <span class="text-muted small">Go to</span>
            </a>

            <a href="{{ url('admin/order/list/all') }}" class="ug-command-item">
                <div class="d-flex align-items-center">
                    <i class="tio-shopping-cart-outlined"></i>
                    <span>{{ translate('All Marketplace & Order Anywhere Orders') }}</span>
                </div>
                <span class="text-muted small">View</span>
            </a>

            <a href="{{ url('admin/users/delivery-man/list') }}" class="ug-command-item">
                <div class="d-flex align-items-center">
                    <i class="tio-car"></i>
                    <span>{{ translate('Driver Fleet & Dispatch Management') }}</span>
                </div>
                <span class="text-muted small">Manage</span>
            </a>

            <a href="{{ url('admin/store/list') }}" class="ug-command-item">
                <div class="d-flex align-items-center">
                    <i class="tio-shop"></i>
                    <span>{{ translate('Merchant & Business Directory') }}</span>
                </div>
                <span class="text-muted small">Explore</span>
            </a>
        </div>

        <!-- Palette Footer Keyboard Hints -->
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top bg-light" style="font-size: 12px; color: #6C757D;">
            <div>
                <span class="mr-2"><kbd class="bg-white text-dark shadow-xs border px-1">↑</kbd> <kbd class="bg-white text-dark shadow-xs border px-1">↓</kbd> {{ translate('Navigate') }}</span>
                <span><kbd class="bg-white text-dark shadow-xs border px-1">↵</kbd> {{ translate('Select') }}</span>
            </div>
            <div>
                <span>Urban Goodz Operating System v2.0</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('ug-command-palette-modal');
    const input = document.getElementById('ug-command-search-input');
    const items = document.querySelectorAll('.ug-command-item');

    function toggleCommandPalette(show) {
        if (show) {
            modal.classList.remove('d-none');
            input.focus();
        } else {
            modal.classList.add('d-none');
        }
    }

    // Toggle shortcut: Cmd+K or Ctrl+K
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            const isHidden = modal.classList.contains('d-none');
            toggleCommandPalette(isHidden);
        }
        if (e.key === 'Escape' && !modal.classList.contains('d-none')) {
            toggleCommandPalette(false);
        }
    });

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            toggleCommandPalette(false);
        }
    });

    // Simple search filtering
    input.addEventListener('input', function () {
        const query = input.value.toLowerCase().trim();
        items.forEach(item => {
            const text = item.innerText.toLowerCase();
            if (text.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>
