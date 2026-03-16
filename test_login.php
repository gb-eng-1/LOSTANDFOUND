<?php
/**
 * Simple login test page to verify credentials work
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .test-section h3 { color: #8b0000; margin-top: 0; }
        .credentials { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .credentials strong { color: #8b0000; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn { padding: 8px 16px; background: #8b0000; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #6e0000; }
    </style>
</head>
<body>
    <h1>🧪 Lost and Found System - Login Test</h1>
    
    <div class="test-section">
        <h3>Admin Login Test</h3>
        <div class="credentials">
            <strong>Email:</strong> admin@ub.edu.ph<br>
            <strong>Password:</strong> admin123
        </div>
        <button class="btn" onclick="testAdminLogin()">Test Admin Login</button>
        <div id="adminResult"></div>
    </div>
    
    <div class="test-section">
        <h3>Student Login Test</h3>
        <div class="credentials">
            <strong>Email:</strong> student@ub.edu.ph<br>
            <strong>Password:</strong> student123
        </div>
        <button class="btn" onclick="testStudentLogin()">Test Student Login</button>
        <div id="studentResult"></div>
    </div>
    
    <div class="test-section">
        <h3>Navigation Links</h3>
        <p>Test the UI navigation:</p>
        <a href="login.php" class="btn">Main Login Page</a>
        <a href="ADMIN/login.php" class="btn">Admin Login Page</a>
        <a href="STUDENT/login.php" class="btn">Student Login Page</a>
    </div>

    <script>
        async function testAdminLogin() {
            const resultDiv = document.getElementById('adminResult');
            resultDiv.innerHTML = 'Testing...';
            
            try {
                const response = await fetch('/LOSTANDFOUND/api/auth/admin/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: 'admin@ub.edu.ph',
                        password: 'admin123'
                    })
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    resultDiv.innerHTML = '<div class="test-result success">✅ Admin login successful!</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-result error">❌ Admin login failed: ' + (data.error || 'Unknown error') + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="test-result error">❌ Network error: ' + error.message + '</div>';
            }
        }
        
        async function testStudentLogin() {
            const resultDiv = document.getElementById('studentResult');
            resultDiv.innerHTML = 'Testing...';
            
            try {
                const response = await fetch('/LOSTANDFOUND/api/auth/student/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: 'student@ub.edu.ph',
                        password: 'student123'
                    })
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    resultDiv.innerHTML = '<div class="test-result success">✅ Student login successful!</div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-result error">❌ Student login failed: ' + (data.error || 'Unknown error') + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="test-result error">❌ Network error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>