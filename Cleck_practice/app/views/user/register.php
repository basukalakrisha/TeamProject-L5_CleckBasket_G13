<?php include 'includes/header.php'; ?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-center mb-4">Register</h1>
    <div class="flex justify-center mb-4">
        <button class="border-b-2 border-black p-2">CUSTOMER</button>
        <button class="p-2">TRADER</button>
    </div>
    <form class="max-w-md mx-auto bg-white p-6 rounded shadow">
        <div class="mb-4">
            <label class="block text-gray-700">First Name</label>
            <input type="text" placeholder="Enter your first name" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Last Name</label>
            <input type="text" placeholder="Enter your last name" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Email</label>
            <input type="email" placeholder="Enter your email" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Contact</label>
            <input type="text" placeholder="Enter your contact" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Address</label>
            <input type="text" placeholder="Address" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Password</label>
            <input type="password" placeholder="Enter your password" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Confirm Password</label>
            <input type="password" placeholder="Reenter your password" class="w-full border rounded p-2">
        </div>
        <button type="submit" class="w-full bg-gray-300 p-2 rounded">REGISTER</button>
        <p class="text-center mt-4">Already have an account? <a href="login.php" class="text-blue-500">Login</a></p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>