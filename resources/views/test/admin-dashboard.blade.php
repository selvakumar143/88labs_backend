<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<style>
body { background:#0f172a; color:white; font-family:Arial; padding:30px;}
button { padding:5px 10px; margin:3px;}
.nav-btn { background:#334155; color:white; }
.section { display:none; margin-top:20px; }
.status-pending { color:orange; }
.status-approved { color:lime; }
.status-rejected { color:red; }
</style>
</head>
<body>

<h2>Admin Dashboard</h2>

<button class="nav-btn" onclick="showAd()">Ad Requests</button>
<button class="nav-btn" onclick="showWallet()">Wallet Topups</button>
<button onclick="logout()">Logout</button>

<div id="adSection" class="section"></div>
<div id="walletSection" class="section"></div>

<script>
const token = localStorage.getItem('token');
if(!token) window.location.href='/test/login';

function showAd(){
    document.getElementById('adSection').style.display='block';
    document.getElementById('walletSection').style.display='none';
    loadAd();
}
function showWallet(){
    document.getElementById('walletSection').style.display='block';
    document.getElementById('adSection').style.display='none';
    loadWallet();
}

async function loadAd(){
    const res=await fetch('/api/admin/ad-account-requests',{
        headers:{'Authorization':'Bearer '+token}
    });
    const data=await res.json();
    let html='<h3>Ad Account Requests</h3>';
    data.data.forEach(r=>{
        html+=`<div>
            ${r.request_id} - ${r.client.name} -
            <span class="status-${r.status}">${r.status}</span>
            ${r.status==='pending'?`
                <button onclick="approveAd(${r.id})">Approve</button>
                <button onclick="rejectAd(${r.id})">Reject</button>
            `:''}
        </div>`;
    });
    document.getElementById('adSection').innerHTML=html;
}

async function loadWallet(){
    const res=await fetch('/api/admin/wallet-topups',{
        headers:{'Authorization':'Bearer '+token}
    });
    const data=await res.json();
    let html='<h3>Wallet Topup Requests</h3>';
    data.data.forEach(r=>{
        html+=`<div>
            ${r.request_id} - ${r.client.name} - $${r.amount} -
            <span class="status-${r.status}">${r.status}</span>
            ${r.status==='pending'?`
                <button onclick="approveWallet(${r.id})">Approve</button>
                <button onclick="rejectWallet(${r.id})">Reject</button>
            `:''}
        </div>`;
    });
    document.getElementById('walletSection').innerHTML=html;
}

async function approveAd(id){
    await fetch('/api/admin/ad-account-requests/'+id+'/approve',{
        method:'PUT',
        headers:{'Authorization':'Bearer '+token}
    });
    loadAd();
}
async function rejectAd(id){
    await fetch('/api/admin/ad-account-requests/'+id+'/reject',{
        method:'PUT',
        headers:{'Authorization':'Bearer '+token}
    });
    loadAd();
}
async function approveWallet(id){
    await fetch('/api/admin/wallet-topups/'+id+'/approve',{
        method:'PUT',
        headers:{'Authorization':'Bearer '+token}
    });
    loadWallet();
}
async function rejectWallet(id){
    await fetch('/api/admin/wallet-topups/'+id+'/reject',{
        method:'PUT',
        headers:{'Authorization':'Bearer '+token}
    });
    loadWallet();
}

function logout(){
    localStorage.clear();
    window.location.href='/test/login';
}

showAd();
</script>
</body>
</html>