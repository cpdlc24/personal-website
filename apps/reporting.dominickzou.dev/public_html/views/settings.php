<?php
require __DIR__ . '/header.php';
?>

<div style="padding-top:4.5rem;padding-bottom:2rem;">
    <div style="margin-bottom:3rem;">
        <h1 class="text-4xl font-medium tracking-tighter">Account Settings</h1>
        <p class="text-gray-400 text-xs tracking-widest uppercase font-semibold" style="margin-top:0.75rem;">
            Manage your profile and security
        </p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2.5rem;">

        <!-- Profile Card -->
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:2.5rem 3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Profile</h3>
            
            <form id="profileForm" class="space-y-8" onsubmit="return false;">
                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">Username</label>
                    <input type="text" id="settingsUsername" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" minlength="2"
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none">
                </div>

                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">Display Name</label>
                    <input type="text" id="settingsDisplayName" value="<?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?>"
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none">
                </div>

                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">
                        Current Password <span class="text-gray-300 normal-case tracking-normal">(required to save changes)</span>
                    </label>
                    <input type="password" id="settingsCurrentPw" required
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none"
                           placeholder="Enter current password">
                </div>

                <div id="profileError" class="text-red-600 text-sm" style="display:none;"></div>
                <div id="profileSuccess" class="text-sm" style="display:none;color:#059669;"></div>

                <button type="button" onclick="saveProfile()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-gray-900 pb-1 transition-colors mt-4">
                    Update Profile
                </button>
            </form>
        </div>

        <!-- Password Card -->
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:2.5rem 3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Change Password</h3>
            
            <form id="passwordForm" class="space-y-8" onsubmit="return false;">
                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">Current Password</label>
                    <input type="password" id="pwCurrentPw" required
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none"
                           placeholder="Enter current password">
                </div>

                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">New Password</label>
                    <input type="password" id="pwNewPw" required minlength="4"
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none"
                           placeholder="Min 4 characters">
                </div>

                <div>
                    <label class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold block mb-2">Confirm New Password</label>
                    <input type="password" id="pwConfirmPw" required minlength="4"
                           class="w-full bg-transparent border-b border-gray-200 py-3 text-base focus:outline-none focus:border-gray-900 transition-colors text-gray-900 rounded-none"
                           placeholder="Re-enter new password">
                </div>

                <div id="pwError" class="text-red-600 text-sm" style="display:none;"></div>
                <div id="pwSuccess" class="text-sm" style="display:none;color:#059669;"></div>

                <button type="button" onclick="changePassword()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-gray-900 pb-1 transition-colors mt-4">
                    Change Password
                </button>
            </form>
        </div>
    </div>

    <!-- Account Details Card -->
    <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:2.5rem 3rem;margin-top:2.5rem;">
        <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Account Details</h3>
        
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3rem;">
            <div>
                <div class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold mb-1">User ID</div>
                <div class="text-lg font-light text-gray-900">#<?php echo (int)$_SESSION['user_id']; ?></div>
            </div>
            <div>
                <div class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold mb-1">Role</div>
                <div class="text-lg font-light text-gray-900"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?></div>
            </div>
            <div>
                <div class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold mb-1">Export Permission</div>
                <div class="text-lg font-light <?php echo !empty($_SESSION['can_export']) ? 'text-green-600' : 'text-gray-400'; ?>">
                    <?php echo !empty($_SESSION['can_export']) ? 'Enabled' : 'Disabled'; ?>
                </div>
            </div>
            <?php if ($_SESSION['role'] === 'analyst' && !empty($_SESSION['sections'])): ?>
            <div>
                <div class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold mb-1">Sections</div>
                <div class="text-lg font-light text-gray-900"><?php echo htmlspecialchars(implode(', ', $_SESSION['sections'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function clearFeedback() {
        ['profileError', 'profileSuccess', 'pwError', 'pwSuccess'].forEach(id => {
            document.getElementById(id).style.display = 'none';
        });
    }

    async function saveProfile() {
        clearFeedback();
        const currentPw = document.getElementById('settingsCurrentPw').value;
        if (!currentPw) {
            showMsg('profileError', 'Current password is required');
            return;
        }

        const payload = {
            current_password: currentPw,
            username: document.getElementById('settingsUsername').value.trim(),
            display_name: document.getElementById('settingsDisplayName').value.trim(),
        };

        try {
            const res = await fetch('/api/auth/me', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok) {
                showMsg('profileError', data.error || 'Update failed');
                return;
            }
            showMsg('profileSuccess', 'Profile updated successfully');
            document.getElementById('settingsCurrentPw').value = '';
            // Refresh page after a moment to show updated header
            setTimeout(() => location.reload(), 1200);
        } catch (e) {
            showMsg('profileError', 'Network error: ' + e.message);
        }
    }

    async function changePassword() {
        clearFeedback();
        const currentPw = document.getElementById('pwCurrentPw').value;
        const newPw = document.getElementById('pwNewPw').value;
        const confirmPw = document.getElementById('pwConfirmPw').value;

        if (!currentPw) {
            showMsg('pwError', 'Current password is required');
            return;
        }
        if (newPw.length < 4) {
            showMsg('pwError', 'New password must be at least 4 characters');
            return;
        }
        if (newPw !== confirmPw) {
            showMsg('pwError', 'Passwords do not match');
            return;
        }

        try {
            const res = await fetch('/api/auth/me', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    current_password: currentPw,
                    new_password: newPw,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                showMsg('pwError', data.error || 'Update failed');
                return;
            }
            showMsg('pwSuccess', 'Password changed successfully');
            document.getElementById('pwCurrentPw').value = '';
            document.getElementById('pwNewPw').value = '';
            document.getElementById('pwConfirmPw').value = '';
        } catch (e) {
            showMsg('pwError', 'Network error: ' + e.message);
        }
    }

    function showMsg(id, msg) {
        const el = document.getElementById(id);
        el.textContent = msg;
        el.style.display = 'block';
    }
</script>

<?php require __DIR__ . '/footer.php'; ?>
