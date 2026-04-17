# Order Management System

## A. Project Description

Order Management System is a Laravel and Livewire application for managing products, stock movement, customer orders, cancellations, logs, and basic reports.

The system supports:

- Product creation and stock adjustment
- Order creation and confirmation
- Full and partial order cancellation
- Automatic stock deduction and restoration
- Product and order activity logs
- Basic reporting for orders, inventory status, and revenue

## B. Setup/Installation Instructions

### Requirements

- PHP 8.3 or higher
- Composer
- Node.js and npm
- MySQL or another Laravel-supported database

### Installation

1. Clone the repository.

    ```bash
    git clone <repository-url>
    cd order-management-system
    ```

2. Install PHP dependencies.

    ```bash
    composer install
    ```

3. Install JavaScript dependencies.

    ```bash
    npm install
    ```

4. Create the environment file.

    ```bash
    cp .env.example .env
    ```

5. Generate the application key.

    ```bash
    php artisan key:generate
    ```

6. Update the database settings in `.env`.

    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=order_management_system
    DB_USERNAME=root
    DB_PASSWORD=
    ```

7. Run the database migrations.

    ```bash
    php artisan migrate
    ```

8. Build frontend assets.

    ```bash
    npm run build
    ```

You can also run the project setup script:

```bash
composer run setup
```

## C. How to Run the Application

Start the Laravel development server:

```bash
php artisan serve
```

In another terminal, start the Vite development server:

```bash
npm run dev
```

Or run the combined development command:

```bash
composer run dev
```

Open the application in your browser:

```text
http://127.0.0.1:8000
```

To run the test suite:

```bash
php artisan test
```

## D. Demo Link

Demo link: _To be added later._

## E. Challenges Faced and Solutions

### Keeping Inventory and Orders Consistent

The main challenge was ensuring that stock quantities stay accurate when orders are confirmed, fully cancelled, or partially cancelled.

Solution: order confirmation deducts stock, while cancellation restores only the cancelled quantities. Partial cancellations are tracked per order item so the system can prevent cancelling more than the remaining ordered quantity.

### Supporting Partial Cancellations

Partial cancellation needs more detail than a simple order status change because each order item may have a different cancelled quantity.

Solution: the system tracks cancelled quantities on order items and recalculates the active order total from the remaining quantities.

### Keeping Activity History Useful

Inventory and order changes should be visible for review without making the interface complicated.

Solution: product logs and order logs were kept simple, readable, and focused on important activity such as additions, deductions, restores, confirmations, and cancellations.

### Keeping the UI Simple

The application needs modals, forms, reports, and logs, but the interface should remain easy to use.

Solution: the UI follows the existing Laravel, Livewire, Flux, and Blade patterns with simple pages, focused modals, and readable summaries.
