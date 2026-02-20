<!DOCTYPE html>
<html>
<head>
<title>Client Dashboard</title>
<style>
body { background:#0f172a; color:white; font-family:Arial; padding:30px;}
button { padding:8px 15px; background:#22c55e; border:none; color:white; cursor:pointer; margin-right:5px;}
.nav-btn { background:#334155; }
.section { display:none; margin-top:20px; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7);}
.modal-content { background:#1e293b; padding:20px; width:400px; margin:100px auto; border-radius:8px;}
input, select, textarea { width:100%; padding:8px; margin:5px 0;}
.status-pending { color:orange; }
.status-approved { color:lime; }
.status-rejected { color:red; }
</style>
</head>
<body>

<h2>Client Dashboard</h2>

<button class="nav-btn" onclick="showAd()">Ad Accounts</button>
<button class="nav-btn" onclick="showWallet()">Wallet</button>
<button onclick="logout()">Logout</button>

<!-- ================= AD SECTION ================= -->
<div id="adSection" class="section">
    <h3>Ad Account Requests</h3>
    <button onclick="openAdModal()">+ Request Ad Account</button>
    <div id="adRequests"></div>
</div>

<!-- ================= WALLET SECTION ================= -->
<div id="walletSection" class="section">
    <h3>Wallet Topups</h3>
    <button onclick="openWalletModal()">+ Add Funds</button>
    <div id="walletRequests"></div>
</div>

<!-- ================= AD MODAL ================= -->
<div class="modal" id="adModal">
  <div class="modal-content">
    <h3>Request Ad Account</h3>
    <form id="adForm">
        <input name="business_name" placeholder="Business Name" required>
        <input type="hidden" name="platform" value="Meta">
        <input name="timezone" placeholder="Timezone" required>
        <input name="country" placeholder="Country" required>
        <select name="currency">
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
        </select>
        <input name="business_manager_id" placeholder="Business Manager ID" required>
        <input name="website_url" placeholder="Website URL" required>
        <input name="account_type" placeholder="Account Type" required>
        <input name="personal_profile" placeholder="Personal FB Profile Link" required>
        <textarea name="notes" placeholder="Notes"></textarea>
        <input type="number" name="number_of_accounts" value="1">
        <button type="submit">Submit</button>
    </form>
  </div>
</div>

<!-- ================= WALLET MODAL ================= -->
<div class="modal" id="walletModal">
  <div class="modal-content">
    <h3>Add Balance</h3>
    <form id="walletForm">
        <input name="amount" placeholder="Enter Amount" required>
        <input name="transaction_hash" placeholder="Enter Transaction Hash" required>
        <button type="submit">Submit</button>
    </form>
  </div>
</div>

<script>
const token = localStorage.getItem('token');
if(!token) window.location.href='/test/login';

function showAd(){
    document.getElementById('adSection').style.display='block';
    document.getElementById('walletSection').style.display='none';
    loadAdRequests();
}
function showWallet(){
    document.getElementById('walletSection').style.display='block';
    document.getElementById('adSection').style.display='none';
    loadWalletRequests();
}
function openAdModal(){ document.getElementById('adModal').style.display='block'; }
function openWalletModal(){ document.getElementById('walletModal').style.display='block'; }
function closeModals(){
    document.getElementById('adModal').style.display='none';
    document.getElementById('walletModal').style.display='none';
}

async function loadAdRequests(){
    const res = await fetch('/api/my-ad-account-requests',{
        headers:{'Authorization':'Bearer '+token}
    });
    const data = await res.json();
    let html='';
    data.data.forEach(r=>{
        html+=`<div>
            ${r.request_id} - ${r.platform} -
            <span class="status-${r.status}">${r.status}</span>
        </div>`;
    });
    document.getElementById('adRequests').innerHTML=html;
}

async function loadWalletRequests(){
    const res = await fetch('/api/my-wallet-topups',{
        headers:{'Authorization':'Bearer '+token}
    });
    const data = await res.json();
    let html='';
    data.data.forEach(r=>{
        html+=`<div>
            ${r.request_id} - $${r.amount} -
            <span class="status-${r.status}">${r.status}</span>
        </div>`;
    });
    document.getElementById('walletRequests').innerHTML=html;
}

document.getElementById('adForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const formData=new FormData(this);
    const res=await fetch('/api/ad-account-request',{
        method:'POST',
        headers:{'Authorization':'Bearer '+token},
        body:formData
    });
    const data=await res.json();
    alert(data.message);
    closeModals();
    loadAdRequests();
});

document.getElementById('walletForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const formData=new FormData(this);
    const res=await fetch('/api/wallet-topup',{
        method:'POST',
        headers:{'Authorization':'Bearer '+token},
        body:formData
    });
    const data=await res.json();
    alert(data.message);
    closeModals();
    loadWalletRequests();
});

function logout(){
    localStorage.clear();
    window.location.href='/test/login';
}

showAd();
</script>
</body>
</html>