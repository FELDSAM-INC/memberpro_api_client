# Memberpro Api
This is Client for memberpro API, provides you with simple and easy to use interface

## Usage

### 1. Install package
```
composer require feldsam-inc/memberpro_api_client
```
### 2. Require autoloader

```
require("vendor/autoload.php");
```

### 3. Add Memberpro_Api for use
```
use MemberproApi\Memberpro_Api;
```

### 4. Create new Memberpro_Api object
```
$endpoint = 'http://10.10.10.10:10/example.asmx';
$api = new Memberpro_Api($endpoint);
```

You can use the getPriceList like that:
```
$priceList = $api->getPriceList();
```

Which returns available items for sale and their ID, name, price, and vat

### 5. Create new Order Object
You will create an Order. it will automatically generates it's ID and space for items.

```
$email = "example@example.com";
$order = $api->createNewOrder($email);
```

### 6. Add at least one item to order
```

$item = [
    'id_item' => 100,
    'name' => "test",
    'code_vat'=> 0,     //vat code 0-standard vat, 1-lowered vat, 2-without vat || 0 - 21%, 1 - 15%, 0 - 0%
    'count' => 1,
    'price_with_vat_per_each' => 100,
];

$rowID = $order->addItem($item);
```

Return the ID of item's row. ID can be used for editing, deleting items (not included yet).

### 7. Finish Order
```
$vouchers = $order->orderFinish();
```

It will mark Order as paid. return the vouchers, but you can get the vouchers anytime with using getVouchers method.
```
$voucher = $order->getVouchers();
```

## Objects and Methods

### Memberpro_API

**CreateNewOrder**: Creates and returns new Order object.

**getPriceList**: Get available items for sale.

### Order

**addItem**: Add item to the Order and returns it's row id (ID_RADEK).

**orderFinish**: Finish the order and return vouchers.

**getVouchers**: Return Vouchers.

