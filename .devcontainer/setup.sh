#!/bin/bash
set -e

echo "🚀 Setting up Laravel 12 development environment..."

# Disable Xdebug to avoid noise
echo "🔇 Disabling Xdebug..."
echo 'xdebug.mode=off' | sudo tee -a /usr/local/etc/php/conf.d/xdebug.ini > /dev/null

# Install PHP extensions
echo "📦 Installing PHP extensions..."
sudo apt-get update
sudo apt-get install -y php8.3-sqlite3 php8.3-mysql php8.3-pgsql php8.3-redis php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip php8.3-gd

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install

# Install Node dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Setup environment
if [ ! -f .env ]; then
    echo "⚙️  Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Configure Codespaces URLs if in Codespaces
if [ -n "$CODESPACE_NAME" ]; then
    echo "🌐 Configuring Codespaces URLs..."
    sed -i "s|APP_URL=.*|APP_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}|" .env
    
    # Add VITE_DEV_SERVER_URL if not present
    if ! grep -q "VITE_DEV_SERVER_URL" .env; then
        echo "VITE_DEV_SERVER_URL=https://${CODESPACE_NAME}-5173.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}" >> .env
    else
        sed -i "s|VITE_DEV_SERVER_URL=.*|VITE_DEV_SERVER_URL=https://${CODESPACE_NAME}-5173.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}|" .env
    fi
    
    # Add ASSET_URL if not present
    if ! grep -q "ASSET_URL" .env; then
        echo "ASSET_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}" >> .env
    else
        sed -i "s|ASSET_URL=.*|ASSET_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}|" .env
    fi
fi

# Setup database
if [ ! -f database/database.sqlite ]; then
    echo "🗄️  Creating SQLite database..."
    touch database/database.sqlite
fi

# Run migrations
echo "🗄️  Running migrations..."
php artisan migrate --force

# Seed roles and permissions
echo "🌱 Seeding roles and permissions..."
php artisan db:seed --class=RolePermissionSeeder --force

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link

# Ensure correct permissions
echo "🔒 Setting permissions..."
chmod -R 775 storage bootstrap/cache
mkdir -p storage/app/public/banners

# Create test users
echo "👥 Creating test users..."
php artisan tinker --execute="
\$admin = App\Models\User::firstOrCreate(
    ['email' => 'admin@example.com'],
    ['name' => 'Admin User', 'vatsim_cid' => '1000001', 'vatsim_rating' => 'S3']
);
\$admin->assignRole('admin');
echo '✅ Admin user: admin@example.com\n';

\$mod = App\Models\User::firstOrCreate(
    ['email' => 'mod@example.com'],
    ['name' => 'Moderator User', 'vatsim_cid' => '1000002', 'vatsim_rating' => 'C1']
);
\$mod->assignRole('moderator');
echo '✅ Moderator user: mod@example.com\n';

\$user = App\Models\User::firstOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'Regular User', 'vatsim_cid' => '1000003', 'vatsim_rating' => 'S1']
);
\$user->assignRole('user');
echo '✅ Regular user: user@example.com\n';
"

echo ""
echo "✨ Setup complete! Run 'composer run dev' to start the development server."
echo ""
echo "📝 Test users created:"
echo "   - admin@example.com (Admin)"
echo "   - mod@example.com (Moderator)"
echo "   - user@example.com (User)"
echo ""
echo "💡 Use Dev Login in the navbar to authenticate without OAuth."
