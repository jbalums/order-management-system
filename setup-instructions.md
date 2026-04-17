# Setup Instructions

## A. Database Setup Steps

1. Install PHP dependencies:

    ```bash
    composer install
    ```

2. Install JavaScript dependencies:

    ```bash
    npm install
    ```

3. Create the environment file if it does not exist:

    ```bash
    cp .env.example .env
    ```

4. Generate the application key:

    ```bash
    php artisan key:generate
    ```

5. Configure the database connection in `.env`.

6. Run migrations:

    ```bash
    php artisan migrate
    ```

7. Build frontend assets:

    ```bash
    npm run build
    ```

You can also run the project setup script:

```bash
composer run setup
```

## B. Environment Configuration

The default `.env.example` uses SQLite:

```env
DB_CONNECTION=sqlite
```

For SQLite, make sure the database file exists before running migrations:

```bash
touch database/database.sqlite
php artisan migrate
```

For MySQL, update the database variables in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_management_system
DB_USERNAME=root
DB_PASSWORD=
```

Useful local defaults:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

After changing environment values, clear cached configuration:

```bash
php artisan config:clear
```

## C. Sample Data Instructions

Run the database seeder to create the default test user:

```bash
php artisan db:seed
```

The default seeded account is:

```text
Name: Test User
Email: test@example.com
```

Use the Products page to add products and initial stock. Use the Orders page to create draft orders from those products, then confirm orders to generate inventory and order activity logs.

To refresh the database and seed it again during local development:

```bash
php artisan migrate:fresh --seed
```
