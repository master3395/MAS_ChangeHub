# Contabo API Setup Guide

## ❌ Current Issue
The API authentication is failing with "Invalid user credentials" error. This means the API credentials need to be properly configured in your Contabo account.

## 🔧 Required Steps

### 1. Access Contabo Customer Control Panel
1. Go to [https://my.contabo.com](https://my.contabo.com)
2. Log in with your regular Contabo account credentials

### 2. Enable API Access
1. Navigate to **API** section in the control panel
2. Enable API access for your account
3. Note down the **Client ID** and **Client Secret** (these look correct in your config)

### 3. Create API User
1. In the API section, create a new **API User**
2. Set a **specific API password** (this is different from your regular login password)
3. Note down the **API User email** and **API password**

### 4. Update Configuration
Once you have the correct API credentials, update `/home/MAS_ChangeHub/contabo/config.php`:

```php
define('CONTABO_API_USER', 'your-actual-api-user-email@example.com');
define('CONTABO_API_PASSWORD', 'your-actual-api-password');
```

## 🔍 Current Configuration Status

✅ **Client ID**: `INT-506877` (appears correct)
✅ **Client Secret**: `b170d60f-879c-4fee-9928-af2257c36690` (appears correct)
❌ **API User**: `kimskorgenes@hotmail.com` (needs verification)
❌ **API Password**: `96Kaktus571!1` (needs verification)

## 🧪 Test After Setup

Once you've updated the credentials, test with:

```bash
cd /home/MAS_ChangeHub/contabo
php test-credentials.php
```

## 📚 Contabo API Documentation

- **API Documentation**: https://api.contabo.com/#tag/Snapshots
- **Authentication**: https://api.contabo.com/#section/Authentication
- **Getting Started**: https://api.contabo.com/#section/Getting-Started

## 🔑 Important Notes

1. **API Password ≠ Login Password**: The API password is specifically created for API access
2. **API User**: This might be a different email or user account than your main login
3. **Permissions**: Ensure the API user has permissions to manage snapshots
4. **Account Status**: Make sure your Contabo account has API access enabled

## 🚨 Security Reminder

- Never share your API credentials
- Keep the config.php file secure
- The API password should be different from your regular password
- Consider rotating API credentials regularly

## 📞 Support

If you continue having issues:
1. Check Contabo support documentation
2. Contact Contabo support for API access issues
3. Verify your account has the necessary permissions for snapshot management
