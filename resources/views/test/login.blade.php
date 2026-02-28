<!DOCTYPE html>
<html>
<head>
    <title>88labs Login</title>
    <style>
        body { background:#0f172a; color:white; font-family:Arial; text-align:center; padding-top:100px;}
        input, select { padding:10px; margin:10px; width:250px;}
        button { padding:10px 20px; background:#3b82f6; color:white; border:none; cursor:pointer;}
        .card { background:#1e293b; padding:40px; width:350px; margin:auto; border-radius:8px;}
    </style>
</head>
<body>

<div class="card">
    <h2>88labs Portal Login</h2>

    <select id="role">
        <option value="customer">Client Portal</option>
        <option value="admin">Admin Portal</option>
    </select><br>

    <input type="email" id="email" placeholder="Email"><br>
    <input type="password" id="password" placeholder="Password"><br>

    <button onclick="login()">Sign In</button>
</div>

<script>
async function login() {

    let role = document.getElementById('role').value;

    let url = role === 'admin'
        ? '/api/admin/login'
        : '/api/customer/login';

    const res = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        })
    });

    const data = await res.json();

    if(data.status === 'success'){
        localStorage.setItem('token', data.token);
        localStorage.setItem('role', role);

        if(role === 'admin'){
            window.location.href = '/test/admin-dashboard';
        } else {
            window.location.href = '/test/client-dashboard';
        }
    } else {
        alert(data.message);
    }
}
</script>

</body>
</html>