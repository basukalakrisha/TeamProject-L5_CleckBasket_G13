<?php include 'includes/header.php'; ?>

<div class="container mx-auto p-4">
    <h1 class="text-3xl font-bold text-center mb-4">Login</h1>
    <div class="flex justify-center mb-4">
        <button class="border-b-2 border-black p-2">CUSTOMER</button>
        <button class="p-2">TRADER</button>
    </div>
    <form class="max-w-md mx-auto bg-white p-6 rounded shadow">
        <div class="mb-4">
            <label class="block text-gray-700">Email</label>
            <input type="email" placeholder="Enter your email" class="w-full border rounded p-2">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Password</label>
            <input type="password" placeholder="Enter your password" class="w-full border rounded p-2">
        </div>
        <p class="text-blue-500 mb-4">Forgot password? Click here</p>
        <button type="submit" class="w-full bg-gray-300 p-2 rounded">LOGIN</button>
        <p class="text-center mt-4">Don't have an account? <a href="register.php" class="text-blue-500">Sign up</a></p>
    </form>
</div>

<?php include 'includes/footer.php'; ?>