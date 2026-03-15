<?php
require __DIR__ . '/header.php';

$callerRole = $_SESSION['role'];
$isSuperAdmin = ($callerRole === 'super_admin');

// Available roles this caller can assign
$assignableRoles = $isSuperAdmin
    ? ['super_admin', 'admin', 'analyst', 'viewer']
    : ['analyst', 'viewer'];
?>

<!-- Modal + Form Styles -->
<style>
    /* Shared modal system */
    .admin-modal-overlay {
        position: fixed; inset: 0; z-index: 50;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none;
        transition: opacity 0.35s ease;
    }
    .admin-modal-overlay.visible { opacity: 1; pointer-events: auto; }
    .admin-modal-backdrop {
        position: absolute; inset: 0;
        background: rgba(0,0,0,0);
        backdrop-filter: blur(0px); -webkit-backdrop-filter: blur(0px);
        transition: background 0.35s ease, backdrop-filter 0.35s ease, -webkit-backdrop-filter 0.35s ease;
    }
    .admin-modal-overlay.visible .admin-modal-backdrop {
        background: rgba(0,0,0,0.08);
        backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
    }
    .admin-modal-card {
        position: relative; width: 100%; margin: 0 1.5rem;
        background: rgba(255,255,255,0.98);
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
        transform: scale(0.95) translateY(10px);
        opacity: 0;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1) 0.25s, opacity 0.3s ease 0.25s;
    }
    .admin-modal-overlay.visible .admin-modal-card { transform: scale(1) translateY(0); opacity: 1; }
    .admin-modal-card.size-md { max-width: 520px; }
    .admin-modal-card.size-sm { max-width: 400px; }

    .modal-header { padding: 2.5rem 3rem 0; }
    .modal-header h2 { font-size: 1.5rem; font-weight: 500; letter-spacing: -0.02em; color: #111; }
    .modal-header p { font-size: 12px; color: #9ca3af; margin-top: 4px; letter-spacing: 0.02em; }
    .modal-body { padding: 2rem 3rem 0; }
    .modal-footer {
        padding: 1.75rem 3rem 2.5rem;
        display: flex; justify-content: flex-end; gap: 0.75rem;
    }
    .modal-footer.center { justify-content: center; }

    /* Buttons */
    .btn-ghost {
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
        color: #9ca3af; background: none;
        border: 1px solid rgba(0,0,0,0.08); border-radius: 10px;
        padding: 10px 22px; cursor: pointer; transition: all 0.15s ease;
    }
    .btn-ghost:hover { color: #374151; border-color: rgba(0,0,0,0.15); }
    .btn-primary {
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
        color: #fff; background: #111; border: none; border-radius: 10px;
        padding: 10px 28px; cursor: pointer; transition: all 0.15s ease;
    }
    .btn-primary:hover { background: #333; }
    .btn-danger {
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
        color: #fff; background: #dc2626; border: none; border-radius: 10px;
        padding: 10px 28px; cursor: pointer; transition: all 0.15s ease;
    }
    .btn-danger:hover { background: #b91c1c; }

    /* Form elements */
    .form-label {
        display: block; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.1em;
        color: #9ca3af; margin-bottom: 8px;
    }
    .form-input, .form-select {
        width: 100%; background: transparent; border: none;
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 0; font-size: 15px; color: #111;
        outline: none; transition: border-color 0.2s ease;
        border-radius: 0; font-family: inherit;
    }
    .form-input:focus, .form-select:focus { border-bottom-color: #111; }
    .form-input:disabled { opacity: 0.45; }
    .form-select { cursor: pointer; -webkit-appearance: none; }
    .form-hint { font-size: 11px; color: #d1d5db; font-weight: 400; text-transform: none; letter-spacing: normal; }
    .form-error-msg {
        font-size: 13px; color: #dc2626;
        padding: 10px 16px; background: rgba(220,38,38,0.04);
        border-radius: 10px; display: none;
    }
    .form-check-row {
        display: flex; align-items: center; gap: 10px;
        cursor: pointer; font-size: 13px; color: #374151;
    }
    .form-check-row input[type="checkbox"] { accent-color: #111; width: 16px; height: 16px; }
    .modal-divider { height: 1px; background: rgba(0,0,0,0.04); }

    /* Custom filter dropdowns */
    .ss-wrapper { position: relative; display: inline-block; }
    .ss-btn {
        font-size: 11px; font-weight: 500; padding: 6px 28px 6px 14px;
        border-radius: 8px; border: 1px solid rgba(0,0,0,0.08);
        background: rgba(255,255,255,0.95); color: #374151;
        cursor: pointer; outline: none; letter-spacing: 0.03em;
        white-space: nowrap; position: relative; text-align: left;
        transition: border-color 0.15s, box-shadow 0.15s;
        min-width: 90px;
    }
    .ss-btn::after {
        content: ''; position: absolute; right: 10px; top: 50%;
        transform: translateY(-50%); border-left: 4px solid transparent;
        border-right: 4px solid transparent; border-top: 4px solid #9ca3af;
    }
    .ss-btn:hover { border-color: rgba(0,0,0,0.18); }
    .ss-btn.active { border-color: rgb(37,99,235); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
    .ss-panel {
        display: none; position: absolute; top: calc(100% + 4px); left: 0;
        z-index: 100; background: #fff; border: 1px solid rgba(0,0,0,0.08);
        border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        min-width: 160px; max-height: 300px; overflow-y: auto;
        padding: 6px 0; animation: ssPanelIn 0.12s ease-out;
    }
    .ss-panel.open { display: block; }
    @keyframes ssPanelIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .ss-panel label {
        display: flex; align-items: center; gap: 8px;
        padding: 6px 14px; font-size: 11px; font-weight: 500;
        color: #374151; cursor: pointer; transition: background 0.1s;
        letter-spacing: 0.02em;
    }
    .ss-panel label:hover { background: rgba(37,99,235,0.06); }
    .ss-panel input[type="radio"] {
        accent-color: rgb(37,99,235); width: 14px; height: 14px;
        cursor: pointer; flex-shrink: 0;
    }
    .ss-panel label.ss-selected {
        background: rgba(37,99,235,0.06); color: rgb(37,99,235); font-weight: 600;
    }
</style>

<div style="padding-top:4.5rem;padding-bottom:2rem;">
    <div class="flex justify-between items-center" style="margin-bottom:3rem;">
        <div>
            <h1 class="text-4xl font-medium tracking-tighter">User Management</h1>
            <p class="text-gray-400 text-xs tracking-widest uppercase font-semibold" style="margin-top:0.75rem;">
                <?php echo $isSuperAdmin ? 'Super Admin — Full Access' : 'Admin — Analyst & Viewer Management'; ?>
            </p>
        </div>
        <button onclick="openCreateModal()" class="text-xs uppercase tracking-widest font-semibold flex items-center gap-2 transition-all hover:shadow-md" style="background:rgba(255,255,255,0.95);border:1px solid rgba(0,0,0,0.08);border-radius:10px;padding:10px 20px;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Create User
        </button>
    </div>

    <!-- Filters -->
    <div id="filtersBar" style="display:flex;gap:0.75rem;margin-bottom:1.5rem;align-items:center;flex-wrap:wrap;">
        <span class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="margin-right:0.25rem;">Filters</span>

        <div class="ss-wrapper" data-filter="filterRole">
            <input type="hidden" id="filterRole" value="">
            <button type="button" class="ss-btn">All Roles</button>
            <div class="ss-panel">
                <label class="ss-selected"><input type="radio" name="fr" value="" checked> All Roles</label>
                <label><input type="radio" name="fr" value="super_admin"> Super Admin</label>
                <label><input type="radio" name="fr" value="admin"> Admin</label>
                <label><input type="radio" name="fr" value="analyst"> Analyst</label>
                <label><input type="radio" name="fr" value="viewer"> Viewer</label>
            </div>
        </div>

        <div class="ss-wrapper" data-filter="filterSections">
            <input type="hidden" id="filterSections" value="">
            <button type="button" class="ss-btn">All Sections</button>
            <div class="ss-panel">
                <label class="ss-selected"><input type="radio" name="fs" value="" checked> All Sections</label>
                <label><input type="radio" name="fs" value="performance"> Performance</label>
                <label><input type="radio" name="fs" value="activity"> Behavioral</label>
                <label><input type="radio" name="fs" value="none"> No Sections</label>
            </div>
        </div>

        <div class="ss-wrapper" data-filter="filterExport">
            <input type="hidden" id="filterExport" value="">
            <button type="button" class="ss-btn">All Export</button>
            <div class="ss-panel">
                <label class="ss-selected"><input type="radio" name="fe" value="" checked> All Export</label>
                <label><input type="radio" name="fe" value="yes"> Can Export</label>
                <label><input type="radio" name="fe" value="no"> Cannot Export</label>
            </div>
        </div>

        <div class="ss-wrapper" data-filter="filterCreated">
            <input type="hidden" id="filterCreated" value="">
            <button type="button" class="ss-btn">All Dates</button>
            <div class="ss-panel">
                <label class="ss-selected"><input type="radio" name="fc" value="" checked> All Dates</label>
                <label><input type="radio" name="fc" value="today"> Today</label>
                <label><input type="radio" name="fc" value="7d"> Last 7 Days</label>
                <label><input type="radio" name="fc" value="30d"> Last 30 Days</label>
            </div>
        </div>

        <button onclick="clearFilters()" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;cursor:pointer;background:none;border:none;padding:6px 8px;transition:color 0.15s;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#9ca3af'">Clear</button>
    </div>

    <!-- Users Table -->
    <div id="usersTableContainer" style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:2.5rem 3rem;">
        <table class="w-full text-sm" id="usersTable">
            <thead>
                <tr class="text-left text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="border-bottom:1px solid rgba(0,0,0,0.06);">
                    <th class="pb-6 pr-6">ID</th>
                    <th class="pb-6 pr-6">Username</th>
                    <th class="pb-6 pr-6">Display Name</th>
                    <th class="pb-6 pr-6">Role</th>
                    <th class="pb-6 pr-6">Sections</th>
                    <th class="pb-6 pr-6">Export</th>
                    <th class="pb-6 pr-6">Created</th>
                    <th class="pb-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="usersBody" class="divide-y divide-gray-50">
                <tr><td colspan="8" class="py-10 text-center text-gray-400">Loading users...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Create / Edit Modal                                                    -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="userModal" class="admin-modal-overlay">
    <div class="admin-modal-backdrop" onclick="closeModal()"></div>
    <div class="admin-modal-card size-md">
        <div class="modal-header">
            <h2 id="modalTitle">Create User</h2>
            <p id="modalSubtitle">Add a new user to the analytics platform</p>
        </div>
        <div class="modal-body">
            <form id="userForm" onsubmit="return false;" style="display:flex;flex-direction:column;gap:1.75rem;">
                <input type="hidden" id="editUserId" value="">

                <div>
                    <label class="form-label">Username</label>
                    <input type="text" id="formUsername" required minlength="2" class="form-input" placeholder="Enter username">
                </div>
                <div>
                    <label class="form-label">Display Name</label>
                    <input type="text" id="formDisplayName" class="form-input" placeholder="Full name or alias">
                </div>
                <div>
                    <label class="form-label">Password <span id="passwordHint" class="form-hint"></span></label>
                    <input type="password" id="formPassword" minlength="4" class="form-input" placeholder="Min 4 characters">
                </div>

                <div class="modal-divider"></div>

                <div>
                    <label class="form-label">Role</label>
                    <select id="formRole" class="form-select" onchange="toggleSections()">
                        <?php foreach ($assignableRoles as $r): ?>
                        <option value="<?php echo $r; ?>"><?php echo ucfirst(str_replace('_', ' ', $r)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="sectionsGroup" style="display:none;">
                    <label class="form-label" style="margin-bottom:12px;">Page Sections</label>
                    <div style="display:flex;gap:1.5rem;">
                        <label class="form-check-row">
                            <input type="checkbox" id="secPerformance" value="performance"> Performance
                        </label>
                        <label class="form-check-row">
                            <input type="checkbox" id="secActivity" value="activity"> Behavioral
                        </label>
                    </div>
                </div>

                <div id="exportGroup">
                    <label class="form-check-row">
                        <input type="checkbox" id="formCanExport">
                        <span>Can export data to PDF</span>
                    </label>
                </div>

                <div id="formError" class="form-error-msg"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal()" class="btn-ghost">Cancel</button>
            <button type="button" onclick="submitUserForm()" class="btn-primary">Save</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Delete Confirmation Modal                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="deleteModal" class="admin-modal-overlay">
    <div class="admin-modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="admin-modal-card size-sm" style="text-align:center;">
        <div class="modal-header" style="padding-bottom:0;">
            <div style="margin:0 auto 1.25rem;width:52px;height:52px;border-radius:50%;background:rgba(220,38,38,0.06);display:flex;align-items:center;justify-content:center;">
                <svg width="22" height="22" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </div>
            <h2>Delete User</h2>
            <p style="margin-top:10px;font-size:13px;color:#6b7280;line-height:1.6;">
                Are you sure you want to remove <strong id="deleteUsername" style="color:#111;"></strong>?<br>
                <span style="font-size:11px;color:#9ca3af;">This action cannot be undone.</span>
            </p>
        </div>
        <input type="hidden" id="deleteUserId" value="">
        <div class="modal-body">
            <div id="deleteError" class="form-error-msg" style="text-align:left;"></div>
        </div>
        <div class="modal-footer center">
            <button onclick="closeDeleteModal()" class="btn-ghost">Cancel</button>
            <button onclick="confirmDelete()" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<script>
    const IS_SUPER_ADMIN = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    const CURRENT_USER_ID = <?php echo (int)$_SESSION['user_id']; ?>;

    // ── Data store ───────────────────────────────────────────────────────────
    let allUsers = [];

    // ── Fetch & render users ────────────────────────────────────────────────
    async function loadUsers() {
        try {
            const res = await fetch('/api/auth/users');
            if (!res.ok) throw new Error('Failed to load users');
            allUsers = await res.json();
            applyFilters();
        } catch (e) {
            document.getElementById('usersBody').innerHTML =
                '<tr><td colspan="8" class="py-8 text-center text-red-500">' + e.message + '</td></tr>';
        }
    }

    // ── Filters ─────────────────────────────────────────────────────────────
    function applyFilters() {
        let filtered = [...allUsers];
        const role = document.getElementById('filterRole').value;
        if (role) filtered = filtered.filter(u => u.role === role);
        const sec = document.getElementById('filterSections').value;
        if (sec === 'none') filtered = filtered.filter(u => !u.sections || !u.sections.length);
        else if (sec) filtered = filtered.filter(u => u.sections && u.sections.includes(sec));
        const exp = document.getElementById('filterExport').value;
        if (exp === 'yes') filtered = filtered.filter(u => u.can_export);
        else if (exp === 'no') filtered = filtered.filter(u => !u.can_export);
        const created = document.getElementById('filterCreated').value;
        if (created) {
            const now = new Date();
            let cutoff;
            if (created === 'today') cutoff = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            else if (created === '7d') cutoff = new Date(now - 7 * 86400000);
            else if (created === '30d') cutoff = new Date(now - 30 * 86400000);
            if (cutoff) filtered = filtered.filter(u => u.created_at && new Date(u.created_at) >= cutoff);
        }
        renderUsers(filtered);
    }

    function clearFilters() {
        document.querySelectorAll('.ss-wrapper').forEach(w => {
            const hidden = w.querySelector('input[type="hidden"]');
            hidden.value = '';
            w.querySelector('.ss-btn').textContent = w.querySelector('.ss-panel label').textContent.trim();
            w.querySelectorAll('label').forEach(l => l.classList.remove('ss-selected'));
            const firstLabel = w.querySelector('.ss-panel label');
            firstLabel.classList.add('ss-selected');
            firstLabel.querySelector('input[type="radio"]').checked = true;
        });
        applyFilters();
    }

    // ── Custom dropdown panel logic ─────────────────────────────────────────
    document.querySelectorAll('.ss-wrapper').forEach(wrapper => {
        const btn = wrapper.querySelector('.ss-btn');
        const panel = wrapper.querySelector('.ss-panel');
        const hidden = wrapper.querySelector('input[type="hidden"]');
        const radios = panel.querySelectorAll('input[type="radio"]');
        const labels = panel.querySelectorAll('label');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close other open panels first
            document.querySelectorAll('.ss-panel.open').forEach(p => {
                if (p !== panel) { p.classList.remove('open'); p.closest('.ss-wrapper').querySelector('.ss-btn').classList.remove('active'); }
            });
            panel.classList.toggle('open');
            btn.classList.toggle('active');
        });

        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                labels.forEach(l => l.classList.remove('ss-selected'));
                radio.closest('label').classList.add('ss-selected');
                btn.textContent = radio.closest('label').textContent.trim();
                panel.classList.remove('open');
                btn.classList.remove('active');
                hidden.value = radio.value;
                applyFilters();
            });
        });
    });

    // Close panels on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.ss-panel.open').forEach(p => {
            p.classList.remove('open');
            p.closest('.ss-wrapper').querySelector('.ss-btn').classList.remove('active');
        });
    });
    document.querySelectorAll('.ss-panel').forEach(p => p.addEventListener('click', e => e.stopPropagation()));

    function renderUsers(users) {
        const tbody = document.getElementById('usersBody');
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="py-10 text-center text-gray-400">No users match the current filters</td></tr>';
            return;
        }
        const roleBadgeColors = {
            'super_admin': 'background:#f0f0ff;color:#4338ca;',
            'admin':       'background:#fef3c7;color:#92400e;',
            'analyst':     'background:#ecfdf5;color:#065f46;',
            'viewer':      'background:#f3f4f6;color:#6b7280;',
        };
        tbody.innerHTML = users.map(u => {
            const badgeStyle = roleBadgeColors[u.role] || '';
            const sections = u.sections && u.sections.length ? u.sections.join(', ') : '—';
            const exportIcon = u.can_export
                ? '<span style="color:#059669;">✓</span>'
                : '<span style="color:#d1d5db;">✗</span>';
            const created = u.created_at ? u.created_at.substring(0, 10) : '—';
            const isSelf = (u.id == CURRENT_USER_ID);
            const canEdit = IS_SUPER_ADMIN || (!['super_admin','admin'].includes(u.role));
            let actions = '';
            if (canEdit) {
                actions += `<button onclick="openEditModal(${u.id})" class="text-xs uppercase tracking-widest font-semibold opacity-40 hover:opacity-100 transition-opacity" style="margin-right:1.25rem;">Edit</button>`;
                if (!isSelf) {
                    actions += `<button onclick="openDeleteModal(${u.id}, '${u.username.replace(/'/g, "\\'")}')" class="text-xs uppercase tracking-widest font-semibold text-red-600 opacity-40 hover:opacity-100 transition-opacity">Delete</button>`;
                }
            }
            return `<tr class="hover:bg-gray-50/50 transition-colors">
                <td class="py-6 pr-6 text-gray-400">${u.id}</td>
                <td class="py-6 pr-6 font-medium">${escHtml(u.username)}</td>
                <td class="py-6 pr-6">${escHtml(u.display_name || '—')}</td>
                <td class="py-6 pr-6"><span style="padding:3px 12px;border-radius:999px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;${badgeStyle}">${u.role.replace('_', ' ')}</span></td>
                <td class="py-6 pr-6 text-gray-500">${sections}</td>
                <td class="py-6 pr-6">${exportIcon}</td>
                <td class="py-6 pr-6 text-gray-400">${created}</td>
                <td class="py-6 text-right" style="white-space:nowrap;">${actions}</td>
            </tr>`;
        }).join('');
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ── User data cache for edit ────────────────────────────────────────────
    let usersCache = [];
    async function fetchUsersCache() {
        const res = await fetch('/api/auth/users');
        if (res.ok) usersCache = await res.json();
    }

    // ── Animated modal open / close ─────────────────────────────────────────
    function showModal(id) {
        const el = document.getElementById(id);
        el.style.display = 'flex';
        // Force reflow so transition fires
        el.offsetHeight;
        el.classList.add('visible');
    }
    function hideModal(id) {
        const el = document.getElementById(id);
        el.classList.remove('visible');
        el.addEventListener('transitionend', function handler() {
            el.removeEventListener('transitionend', handler);
            el.style.display = 'none';
        });
    }

    // ── Create / Edit modal controls ────────────────────────────────────────
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Create User';
        document.getElementById('modalSubtitle').textContent = 'Add a new user to the analytics platform';
        document.getElementById('editUserId').value = '';
        document.getElementById('formUsername').value = '';
        document.getElementById('formUsername').disabled = false;
        document.getElementById('formDisplayName').value = '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = true;
        document.getElementById('formPassword').placeholder = 'Min 4 characters';
        document.getElementById('passwordHint').textContent = '';
        document.getElementById('formRole').value = 'analyst';
        document.getElementById('formCanExport').checked = false;
        document.getElementById('secPerformance').checked = false;
        document.getElementById('secActivity').checked = false;
        document.getElementById('formError').style.display = 'none';
        toggleSections();
        showModal('userModal');
    }

    async function openEditModal(userId) {
        await fetchUsersCache();
        const u = usersCache.find(x => x.id == userId);
        if (!u) return alert('User not found');

        document.getElementById('modalTitle').textContent = 'Edit User';
        document.getElementById('modalSubtitle').textContent = 'Update account for ' + u.username;
        document.getElementById('editUserId').value = u.id;
        document.getElementById('formUsername').value = u.username;
        document.getElementById('formUsername').disabled = true;
        document.getElementById('formDisplayName').value = u.display_name || '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = false;
        document.getElementById('formPassword').placeholder = 'Leave blank to keep current';
        document.getElementById('passwordHint').textContent = '(optional)';
        document.getElementById('formRole').value = u.role;
        document.getElementById('formCanExport').checked = u.can_export;
        document.getElementById('secPerformance').checked = (u.sections || []).includes('performance');
        document.getElementById('secActivity').checked = (u.sections || []).includes('activity');
        document.getElementById('formError').style.display = 'none';
        toggleSections();
        showModal('userModal');
    }

    function closeModal() { hideModal('userModal'); }

    function toggleSections() {
        const role = document.getElementById('formRole').value;
        document.getElementById('sectionsGroup').style.display = (role === 'analyst') ? 'block' : 'none';
        if (role === 'viewer') {
            document.getElementById('exportGroup').style.display = 'none';
            document.getElementById('formCanExport').checked = false;
        } else {
            document.getElementById('exportGroup').style.display = 'block';
        }
    }

    // ── Submit create / edit ─────────────────────────────────────────────────
    async function submitUserForm() {
        const editId = document.getElementById('editUserId').value;
        const isEdit = !!editId;
        const sections = [];
        if (document.getElementById('secPerformance').checked) sections.push('performance');
        if (document.getElementById('secActivity').checked) sections.push('activity');
        const payload = {
            username:     document.getElementById('formUsername').value.trim(),
            display_name: document.getElementById('formDisplayName').value.trim(),
            role:         document.getElementById('formRole').value,
            can_export:   document.getElementById('formCanExport').checked,
            sections:     sections,
        };
        const pw = document.getElementById('formPassword').value;
        if (pw) payload.password = pw;
        else if (!isEdit) { showFormError('Password is required for new users'); return; }
        try {
            let res;
            if (isEdit) {
                res = await fetch(`/api/auth/users/${editId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (payload.role === 'analyst') {
                    await fetch(`/api/auth/users/${editId}/sections`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sections }),
                    });
                }
            } else {
                res = await fetch('/api/auth/users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            }
            if (!res.ok) { const err = await res.json(); showFormError(err.error || 'Request failed'); return; }
            closeModal();
            loadUsers();
        } catch (e) { showFormError('Network error: ' + e.message); }
    }

    function showFormError(msg) {
        const el = document.getElementById('formError');
        el.textContent = msg;
        el.style.display = 'block';
    }

    // ── Delete modal ────────────────────────────────────────────────────────
    function openDeleteModal(userId, username) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUsername').textContent = username;
        document.getElementById('deleteError').style.display = 'none';
        showModal('deleteModal');
    }
    function closeDeleteModal() { hideModal('deleteModal'); }

    async function confirmDelete() {
        const id = document.getElementById('deleteUserId').value;
        try {
            const res = await fetch(`/api/auth/users/${id}`, { method: 'DELETE' });
            if (!res.ok) {
                const err = await res.json();
                const el = document.getElementById('deleteError');
                el.textContent = err.error || 'Delete failed';
                el.style.display = 'block';
                return;
            }
            closeDeleteModal();
            loadUsers();
        } catch (e) {
            const el = document.getElementById('deleteError');
            el.textContent = 'Network error';
            el.style.display = 'block';
        }
    }

    // ── Init ────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', loadUsers);
</script>

<?php require __DIR__ . '/footer.php'; ?>
