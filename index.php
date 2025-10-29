

   <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">👥 User Management</h1>
        
        <button id="showAddBtn" class="btn btn-primary mb-3">➕ Add User</button>
        <div id="alertPlaceholder"></div>
        
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTbody">
                    <tr>
                        <td colspan="5" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <input type="hidden" id="userId">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="saveBtn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    const apiBase = 'api.php';
    let userModal = new bootstrap.Modal(document.getElementById('userModal'));
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    let sseConnection = null;

    function showAlert(message, type='success') {
        alertPlaceholder.innerHTML = `
            <div class="alert alert-${type} alert-dismissible" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        setTimeout(()=> {
            const el = alertPlaceholder.querySelector('.alert');
            if (el) el.remove();
        }, 4000);
    }

    // دالة تحديث الجدول
    function updateUsersTable(users) {
        const tbody = document.getElementById('usersTbody');
        tbody.innerHTML = '';

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No users found</td></tr>';
            return;
        }

        users.forEach((u) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${u.id}</td>
                <td>${escapeHtml(u.name)}</td>
                <td>${escapeHtml(u.email)}</td>
                <td>${formatDate(u.created_at)}</td>
                <td>
                <button class="btn btn-sm btn-warning me-1" onclick="editUser(${u.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">Delete</button>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replaceAll('&','&amp;')
                 .replaceAll('<','&lt;')
                 .replaceAll('>','&gt;')
                 .replaceAll('"','&quot;');
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        return new Date(dateString).toLocaleString();
    }

    // إعداد الاتصال بالـ SSE مع error handling محسن
    function setupSSE() {
        try {
            console.log('🔄 جاري الاتصال بـ SSE...');
            
            // إغلاق الاتصال القديم إذا موجود
            if (sseConnection) {
                sseConnection.close();
            }
            
            sseConnection = new EventSource('sse.php');
            
            sseConnection.addEventListener('connect', function(event) {
                try {
                    if (event.data) {
                        const data = JSON.parse(event.data);
                        console.log('✅ ' + data.message);
                        showAlert('Connected to live updates', 'info');
                    }
                } catch (error) {
                    console.log('📨 رسالة connect:', event.data);
                }
            });
            
            sseConnection.addEventListener('users', function(event) {
                try {
                    if (event.data) {
                        const data = JSON.parse(event.data);
                        console.log('📊 بيانات المستخدمين:', data);
                        
                        if (data.type === 'users_update') {
                            updateUsersTable(data.data);
                            console.log(`✅ تم تحديث ${data.count} مستخدم تلقائياً`);
                        }
                    }
                } catch (error) {
                    console.log('📨 رسالة users:', event.data);
                }
            });
            
            sseConnection.addEventListener('ping', function(event) {
                console.log('❤️ SSE ping received');
            });
            
            sseConnection.addEventListener('error', function(event) {
                try {
                    if (event.data) {
                        const data = JSON.parse(event.data);
                        console.error('❌ خطأ من الخادم:', data.message);
                        showAlert('Live updates error: ' + data.message, 'warning');
                    }
                } catch (error) {
                    console.log('⚠️ رسالة error:', event.data);
                }
            });
            
            sseConnection.addEventListener('close', function(event) {
                console.log('🔴 اتصال SSE أغلق');
                showAlert('Live updates disconnected', 'warning');
                setTimeout(setupSSE, 5000);
            });
            
            sseConnection.onopen = function() {
                console.log('✅ اتصال SSE مفتوح');
            };
            
            sseConnection.onerror = function(event) {
                console.log('⚠️ حدث خطأ في اتصال SSE');
                if (sseConnection.readyState === EventSource.CLOSED) {
                    console.log('🔄 إعادة الاتصال بعد 3 ثواني...');
                    setTimeout(setupSSE, 3000);
                }
            };
            
            console.log('🚀 اتصال SSE مبدئي ناجح');
            return sseConnection;
            
        } catch (error) {
            console.log('❌ فشل إعداد SSE:', error.message);
            showAlert('Live updates not available', 'warning');
            setTimeout(setupSSE, 5000);
        }
    }

    // تحميل المستخدمين أول مرة
    async function fetchUsers() {
        try {
            console.log('📥 جاري تحميل البيانات...');
            const res = await fetch(`${apiBase}?action=list`);
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            
            if (!data.success) { 
                showAlert('Failed to load users', 'danger'); 
                return; 
            }
            
            updateUsersTable(data.data);
            console.log(`✅ تم تحميل ${data.data.length} مستخدم`);
            
        } catch (error) {
            console.log('❌ خطأ في تحميل المستخدمين:', error.message);
            showAlert('Error loading users', 'danger');
        }
    }

    // إضافة مستخدم جديد
    document.getElementById('showAddBtn').addEventListener('click', () => {
        document.getElementById('modalTitle').innerText = 'Add User';
        document.getElementById('userId').value = '';
        document.getElementById('name').value = '';
        document.getElementById('email').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').placeholder = 'Enter password';
        userModal.show();
    });

    // معالجة النموذج
    document.getElementById('userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('userId').value;
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        if (!name || !email) {
            showAlert('Name and Email are required', 'warning'); 
            return;
        }

        const btn = document.getElementById('saveBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
        btn.disabled = true;

        try {
            let url, method;
            if (!id) {
                // create
                url = `${apiBase}?action=create`;
                method = 'POST';
            } else {
                // update
                url = `${apiBase}?action=update`;
                method = 'POST';
            }

            const res = await fetch(url, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, name, email, password})
            });
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            if (data.success) {
                const action = !id ? 'added' : 'updated';
                showAlert(`User ${action} successfully`);
                userModal.hide();
                console.log(`✅ تم ${action} المستخدم`);
            } else {
                showAlert(data.message || 'An error occurred', 'danger');
            }
        } catch (error) {
            console.log('❌ خطأ في الشبكة:', error.message);
            showAlert('Network error: ' + error.message, 'danger');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // تعديل مستخدم
    async function editUser(id) {
        try {
            console.log(`✏️ جاري تحميل بيانات المستخدم ${id}...`);
            const res = await fetch(`${apiBase}?action=list`);
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            const user = data.data.find(u => u.id == id);
            
            if (!user) { 
                showAlert('User not found', 'danger'); 
                return; 
            }

            document.getElementById('modalTitle').innerText = 'Edit User';
            document.getElementById('userId').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('password').value = '';
            document.getElementById('password').placeholder = 'Leave blank to keep current password';
            userModal.show();
            
            console.log(`✅ تم تحميل بيانات المستخدم ${user.name}`);
        } catch (error) {
            console.log('❌ خطأ في تحميل بيانات المستخدم:', error.message);
            showAlert('Error loading user data', 'danger');
        }
    }

    // حذف مستخدم
    async function deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;
        
        try {
            console.log(`🗑️ جاري حذف المستخدم ${id}...`);
            const res = await fetch(`${apiBase}?action=delete`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id})
            });
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const data = await res.json();
            if (data.success) {
                showAlert('User deleted successfully');
                console.log('✅ تم حذف المستخدم');
            } else {
                showAlert(data.message || 'Failed to delete', 'danger');
            }
        } catch (error) {
            console.log('❌ خطأ في حذف المستخدم:', error.message);
            showAlert('Network error: ' + error.message, 'danger');
        }
    }

    // بدء التشغيل عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 System starting...');
        fetchUsers(); // تحميل البيانات أول مرة
        setupSSE();   // بدء الاتصال بالـ SSE
        
        // إضافة أزرار debugging
        addDebugButtons();
    });

    // إضافة أزرار debugging
    function addDebugButtons() {
        const debugDiv = document.createElement('div');
        debugDiv.style.cssText = 'position:fixed; bottom:10px; right:10px; z-index:1000;';
        debugDiv.innerHTML = `
            <div class="btn-group-vertical">
                <button onclick="testSSE()" class="btn btn-sm btn-info mb-1">Test SSE</button>
                <button onclick="manualRefresh()" class="btn btn-sm btn-warning mb-1">Refresh</button>
                <button onclick="checkConnection()" class="btn btn-sm btn-secondary">Check</button>
            </div>
        `;
        document.body.appendChild(debugDiv);
    }

    // دوال debugging محسنة
    function testSSE() {
        console.log('🧪 فحص SSE...');
        fetch('sse.php')
            .then(response => {
                console.log('📡 حالة الـ SSE:', response.status, response.statusText);
                return response.text();
            })
            .then(data => {
                console.log('📨 رد الـ SSE:', data.substring(0, 200));
            })
            .catch(error => {
                console.log('❌ فشل فحص SSE:', error.message);
            });
    }

    function manualRefresh() {
        console.log('🔄 تحديث يدوي...');
        fetchUsers();
    }

    function checkConnection() {
        console.log('🔍 فحص الاتصالات:');
        console.log('- SSE Connection:', sseConnection ? sseConnection.readyState : 'Not connected');
        console.log('- URL:', window.location.href);
    }
    </script>
</body>
</html>