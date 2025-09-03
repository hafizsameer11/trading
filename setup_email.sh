#!/bin/bash

echo "🚀 ProfitTrade Email Setup Script"
echo "=================================="
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ .env file not found!"
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
    echo "✅ .env file created!"
    echo ""
fi

echo "📧 Email Configuration Options:"
echo "1. Gmail SMTP (Recommended for development)"
echo "2. Mailgun (Production ready)"
echo "3. SendGrid (Production ready)"
echo "4. Skip email setup"
echo ""

read -p "Choose an option (1-4): " choice

case $choice in
    1)
        echo ""
        echo "🔧 Setting up Gmail SMTP..."
        echo ""
        read -p "Enter your Gmail address: " gmail_address
        read -p "Enter your Gmail App Password: " gmail_password
        
        # Update .env file
        sed -i '' "s/MAIL_MAILER=.*/MAIL_MAILER=smtp/" .env
        sed -i '' "s/MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/" .env
        sed -i '' "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
        sed -i '' "s/MAIL_USERNAME=.*/MAIL_USERNAME=$gmail_address/" .env
        sed -i '' "s/MAIL_PASSWORD=.*/MAIL_PASSWORD=$gmail_password/" .env
        sed -i '' "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
        sed -i '' "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$gmail_address/" .env
        sed -i '' "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=\"ProfitTrade\"/" .env
        
        echo "✅ Gmail SMTP configured!"
        echo ""
        echo "📋 Next steps:"
        echo "1. Make sure 2FA is enabled on your Gmail account"
        echo "2. Generate an App Password (Google Account > Security > App Passwords)"
        echo "3. Test the configuration: php artisan email:test $gmail_address"
        ;;
    2)
        echo ""
        echo "🔧 Setting up Mailgun..."
        echo ""
        read -p "Enter your Mailgun domain: " mailgun_domain
        read -p "Enter your Mailgun API key: " mailgun_key
        
        # Update .env file
        sed -i '' "s/MAIL_MAILER=.*/MAIL_MAILER=mailgun/" .env
        sed -i '' "s/MAILGUN_DOMAIN=.*/MAILGUN_DOMAIN=$mailgun_domain/" .env
        sed -i '' "s/MAILGUN_SECRET=.*/MAILGUN_SECRET=$mailgun_key/" .env
        sed -i '' "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=noreply@$mailgun_domain/" .env
        sed -i '' "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=\"ProfitTrade\"/" .env
        
        echo "✅ Mailgun configured!"
        echo ""
        echo "📋 Next steps:"
        echo "1. Verify your domain in Mailgun dashboard"
        echo "2. Test the configuration: php artisan email:test your-email@example.com"
        ;;
    3)
        echo ""
        echo "🔧 Setting up SendGrid..."
        echo ""
        read -p "Enter your SendGrid API key: " sendgrid_key
        
        # Update .env file
        sed -i '' "s/MAIL_MAILER=.*/MAIL_MAILER=smtp/" .env
        sed -i '' "s/MAIL_HOST=.*/MAIL_HOST=smtp.sendgrid.net/" .env
        sed -i '' "s/MAIL_PORT=.*/MAIL_PORT=587/" .env
        sed -i '' "s/MAIL_USERNAME=.*/MAIL_USERNAME=apikey/" .env
        sed -i '' "s/MAIL_PASSWORD=.*/MAIL_PASSWORD=$sendgrid_key/" .env
        sed -i '' "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
        sed -i '' "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=noreply@yourdomain.com/" .env
        sed -i '' "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=\"ProfitTrade\"/" .env
        
        echo "✅ SendGrid configured!"
        echo ""
        echo "📋 Next steps:"
        echo "1. Verify your sender email in SendGrid dashboard"
        echo "2. Test the configuration: php artisan email:test your-email@example.com"
        ;;
    4)
        echo ""
        echo "⏭️  Skipping email setup..."
        echo "📝 You can configure email later by editing the .env file"
        ;;
    *)
        echo ""
        echo "❌ Invalid option. Please run the script again."
        exit 1
        ;;
esac

echo ""
echo "🔍 Checking email configuration..."
php artisan email:check

echo ""
echo "🎯 Setup complete! The OTP system is now ready to send real emails."
echo "📧 Test it by running: php artisan email:test your-email@example.com"
