<!DOCTYPE html>
<html>
<head>
    <title>Client Management</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

<h2>Client List</h2>

<button onclick="openModal()" style="float:right;">+ Add Client</button>

<br><br>

@if(session('success'))
    <p style="color:green;">{{ session('success') }}</p>
@endif

<table border="1" width="100%" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Country</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Client Type</th>
        <th>Settlement Mode</th>
        <th>Actions</th>
    </tr>

    @foreach($clients as $client)
    <tr>
        <td>{{ $client->id }}</td>
        <td>{{ $client->clientName }}</td>
        <td>{{ $client->country }}</td>
        <td>{{ $client->email }}</td>
        <td>{{ $client->phone }}</td>
        <td>{{ $client->clientType }}</td>
        <td>{{ $client->settlementMode }}</td>
        <td>
            <button onclick='editClient(@json($client))'>Edit</button>

            <form action="{{ route('clients.destroy', $client->id) }}"
                  method="POST"
                  style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit">Delete</button>
            </form>
        </td>
    </tr>
    @endforeach
</table>

<div id="modal" style="display:none; background:#00000088; position:fixed; top:0; left:0; width:100%; height:100%;">
    <div style="background:white; width:700px; margin:40px auto; padding:25px; border-radius:8px;">
        <h3 id="modalTitle">Add Client</h3>

        <form id="clientForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="method">

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Client Code:
                    <input type="text" name="id" id="id" required>
                </div>
                <div style="flex:1;">
                    Country:
                    <input type="text" name="country" id="country" required>
                </div>
            </div>
            <br>

            Client Name:
            <input type="text" name="clientName" id="clientName" required style="width:100%;">
            <br><br>

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Email:
                    <input type="email" name="email" id="email" required>
                </div>
                <div style="flex:1;">
                    Phone:
                    <input type="text" name="phone" id="phone" required>
                </div>
            </div>
            <br>

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Client Type:
                    <select name="clientType" id="clientType">
                        <option value="Regular">Regular</option>
                        <option value="VIP">VIP</option>
                    </select>
                </div>
                <div style="flex:1;">
                    Niche:
                    <select name="niche" id="niche">
                        <option value="Finance">Finance</option>
                        <option value="Ecommerce">Ecommerce</option>
                        <option value="Gaming">Gaming</option>
                    </select>
                </div>
            </div>
            <br>

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Market Country:
                    <input type="text" name="marketCountry" id="marketCountry">
                </div>
                <div style="flex:1;">
                    Settlement Mode:
                    <input type="text" name="settlementMode" id="settlementMode">
                </div>
            </div>
            <br>

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Statement Cycle:
                    <input type="text" name="statementCycle" id="statementCycle">
                </div>
                <div style="flex:1;">
                    Settlement Currency:
                    <input type="text" name="settlementCurrency" id="settlementCurrency">
                </div>
            </div>
            <br>

            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    Cooperation Start:
                    <input type="date" name="cooperationStart" id="cooperationStart">
                </div>
                <div style="flex:1;">
                    Service Fee Percent:
                    <input type="number" step="0.01" name="serviceFeePercent" id="serviceFeePercent">
                </div>
            </div>
            <br>

            Service Fee Effective Time:
            <input type="datetime-local" name="serviceFeeEffectiveTime" id="serviceFeeEffectiveTime" style="width:100%;">
            <br><br>

            <button type="submit">Save Client</button>
            <button type="button" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>


<script>
function openModal() {
    document.getElementById('modal').style.display = 'block';
    document.getElementById('clientForm').action = "{{ route('clients.store') }}";
    document.getElementById('method').value = "POST";
    document.getElementById('modalTitle').innerText = "Add Client";
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function editClient(client) {
    openModal();
    document.getElementById('modalTitle').innerText = "Edit Client";

    document.getElementById('clientForm').action = "/clients/" + client.id;
    document.getElementById('method').value = "PUT";

    document.getElementById('id').value = client.id;
    document.getElementById('clientName').value = client.clientName;
    document.getElementById('country').value = client.country;
    document.getElementById('email').value = client.email;
    document.getElementById('phone').value = client.phone;
    document.getElementById('clientType').value = client.clientType;
    document.getElementById('niche').value = client.niche;
    document.getElementById('marketCountry').value = client.marketCountry;
    document.getElementById('settlementMode').value = client.settlementMode;
    document.getElementById('statementCycle').value = client.statementCycle;
    document.getElementById('settlementCurrency').value = client.settlementCurrency;
    document.getElementById('cooperationStart').value = client.cooperationStart;
    document.getElementById('serviceFeePercent').value = client.serviceFeePercent;
    document.getElementById('serviceFeeEffectiveTime').value =
        client.serviceFeeEffectiveTime?.replace(' ', 'T');
}
</script>


</body>
</html>
