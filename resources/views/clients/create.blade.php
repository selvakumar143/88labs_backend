<h2>Create Client</h2>

<form method="POST" action="{{ route('clients.store') }}">
    @csrf

    ID: <input type="text" name="id"><br>
    Name: <input type="text" name="clientName"><br>
    Email: <input type="email" name="email"><br>
    Country: <input type="text" name="country"><br>

    User:
    <select name="user_id">
        @foreach($users as $user)
            <option value="{{ $user->id }}">{{ $user->name }}</option>
        @endforeach
    </select>

    <button type="submit">Save</button>
</form>
