<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$flashMessages = getFlashMessages();

// Generate idempotency key for this form submission
$idempotencyKey = bin2hex(random_bytes(32));

// Fetch all active products for the dropdown
$products = fetchAll("SELECT id, sku, name, quantity, unit_price FROM products WHERE is_active = 1 ORDER BY name ASC");
?>
<?php include_once __DIR__ . '/../layouts/header.php'; ?>
<main class="flex-1 overflow-y-auto p-6 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">New Transaction</h1>

            <?php foreach ($flashMessages as $msg): ?>
                <div class="mb-4 px-4 py-3 rounded-lg text-sm
                    <?php echo $msg['type'] === 'error'
                        ? 'bg-red-50 text-red-700'
                        : 'bg-green-50 text-green-700'; ?>">
                    <?php echo e($msg['message']); ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="/actions/products/transaction.php" class="space-y-5" id="transactionForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="idempotency_key" value="<?php echo e($idempotencyKey); ?>">

                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Product *</label>
                    <select id="product_id" name="product_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="updateUnitPrice(this)">
                        <option value="">Select a product</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                    data-sku="<?php echo e($p['sku']); ?>"
                                    data-price="<?php echo (float)$p['unit_price']; ?>"
                                    data-qty="<?php echo (int)$p['quantity']; ?>">
                                <?php echo e("{$p['sku']} - {$p['name']} (Stock: {$p['quantity']})"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type *</label>
                    <select id="type" name="type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="toggleFields()">
                        <option value="">Select type</option>
                        <option value="sale">Sale (Stock Out)</option>
                        <option value="restock">Restock (Stock In)</option>
                        <option value="return">Return (Stock In)</option>
                        <option value="adjustment">Adjustment (+/-)</option>
                    </select>
                </div>

                <div id="quantityField" class="hidden">
                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" min="1" step="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter quantity">
                    <p id="stockHint" class="text-xs text-gray-500 mt-1"></p>
                </div>

                <div id="adjustmentFields" class="hidden">
                    <label for="delta_quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity Change * <span class="text-xs text-gray-400">(positive = stock in, negative = stock out)</span></label>
                    <input type="number" id="delta_quantity" name="delta_quantity" step="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter change (e.g., +10 or -5)">
                </div>

                <div id="unitPriceField">
                    <label for="unit_price" class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                </div>

                <div>
                    <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" maxlength="50"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Optional: invoice #, PO #, etc.">
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Optional notes"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Record Transaction
                    </button>
                    <a href="/transactions" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function toggleFields() {
    const type = document.getElementById('type').value;
    const qtyField = document.getElementById('quantityField');
    const adjFields = document.getElementById('adjustmentFields');
    const stockHint = document.getElementById('stockHint');
    const productSelect = document.getElementById('product_id');
    
    // Hide all conditional fields first
    qtyField.classList.add('hidden');
    adjFields.classList.add('hidden');
    
    if (type === 'adjustment') {
        adjFields.classList.remove('hidden');
    } else if (type) {
        qtyField.classList.remove('hidden');
        // Show stock hint for sale transactions
        if (type === 'sale') {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const stock = selectedOption ? parseInt(selectedOption.dataset.qty) : 0;
            stockHint.textContent = 'Available stock: ' + stock;
            stockHint.className = stock > 0 ? 'text-xs text-green-600 mt-1' : 'text-xs text-red-600 mt-1';
        } else {
            stockHint.textContent = '';
        }
    }
}

function updateUnitPrice(select) {
    const priceInput = document.getElementById('unit_price');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.price) {
        priceInput.value = selectedOption.dataset.price;
    }
    // Update stock hint if sale type is selected
    const type = document.getElementById('type').value;
    if (type === 'sale') {
        const stock = selectedOption ? parseInt(selectedOption.dataset.qty) : 0;
        const stockHint = document.getElementById('stockHint');
        stockHint.textContent = 'Available stock: ' + stock;
        stockHint.className = stock > 0 ? 'text-xs text-green-600 mt-1' : 'text-xs text-red-600 mt-1';
    }
}
</script>
<?php include_once __DIR__ . '/../layouts/footer.php'; ?>