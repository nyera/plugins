# تقرير تقني: المنصة الوسيطة (Yamama Middleware)

## 1. نظرة عامة

المنصة الوسيطة تقع بين متاجر WooCommerce (عبر بلجن Yamama Shipping) ومنصة لمحة (Lamha) للشحن. دورها:

1. **استقبال** طلبات الشحن من المتاجر بصيغة Lamha API
2. **تسعير** الشحنات (أسعار تحددها المنصة الوسيطة، وليس لمحة)
3. **تحصيل** تكلفة الشحن من التاجر عبر Tap (الدفع يصل لحساب المنصة الوسيطة)
4. **إعادة توجيه** الطلبات تلقائيا إلى لمحة عند تفعيل الحساب
5. **استقبال** webhooks من لمحة وتمريرها للمتجر

```
┌──────────────┐        ┌──────────────────┐        ┌──────────────┐
│  WooCommerce │  HTTP   │    Middleware     │  HTTP   │   Lamha API  │
│   Plugin     │◄──────►│  (هذا المشروع)    │◄──────►│ app.lamha.sa │
└──────────────┘        └──────────────────┘        └──────────────┘
       │                        │
       │  Tap Payment           │  تسعير + قاعدة بيانات
       ▼                        ▼
  ┌──────────┐           ┌──────────────┐
  │   Tap    │           │   Database   │
  │  Gateway │           │  (stores,    │
  └──────────┘           │   orders,    │
                         │   pricing)   │
                         └──────────────┘
```

---

## 2. تسجيل المتجر (Store Registration)

عند تثبيت البلجن في أي متجر WooCommerce، يُرسل تلقائيا طلب تسجيل.

### `POST /api/v1/plugin/register`

**Headers المُرسلة من البلجن:**

```
Content-Type: application/json
Accept: application/json
X-Yamama-Store-UUID: {uuid-v4}
X-Yamama-Site-URL: https://store-domain.com/
```

**Request Body:**

```json
{
  "storeUuid": "550e8400-e29b-41d4-a716-446655440000",
  "siteUrl": "https://store-domain.com/",
  "adminUrl": "https://store-domain.com/wp-admin/",
  "storeName": "متجر الأزياء",
  "adminEmail": "admin@store-domain.com",
  "currency": "SAR",
  "locale": "ar",
  "countryCode": "SA",
  "cityName": "الرياض",
  "plugin": "yamama-shipping",
  "pluginVersion": "1.0.0",
  "woocommerceVersion": "9.5.1",
  "wordpressVersion": "6.7",
  "forceRotate": false
}
```

**Expected Response (200):**

```json
{
  "api_token": "yam_tok_xxxxxxxxxxxxxxxxxxxx",
  "hmac_secret": "yam_sec_xxxxxxxxxxxxxxxxxxxx",
}
```

**ملاحظات:**

- `storeUuid` ثابت لكل متجر (يُولّد مرة واحدة ولا يتغير)
- عند `forceRotate: true` يجب توليد token و secret جديدين
- البلجن يجرب أشكال متعددة للـ payload (camelCase و snake_case) - يجب قبول أي منها
- البلجن يجرب أشكال متعددة للـ response - يفضل إرجاع `api_token` و `hmac_secret` في المستوى الأعلى

---

## 3. المصادقة (Authentication)

كل طلب بعد التسجيل يحمل هذه الـ Headers:

```
Content-Type: application/json
X-Yamama-Store-UUID: {store_uuid}
X-Yamama-Site-URL: https://store-domain.com/
Authorization: Bearer {api_token}
X-Yamama-Signature: {hmac_sha256_hex}
```

**حساب الـ Signature:**

```
HMAC-SHA256(request_body_json, hmac_secret) → hex string
```

المنصة الوسيطة يجب أن تتحقق من:

1. أن `store_uuid` مسجل
2. أن `api_token` صحيح لهذا المتجر
3. أن `X-Yamama-Signature` = `HMAC-SHA256(body, store.hmac_secret)`

---

## 4. الـ Endpoints المطلوبة

البلجن يرسل كل طلباته إلى: `{middleware_base_url}/{path}`

حاليا `middleware_base_url` = `http://localhost:4000/api/v1/plugin`

### 4.1 شركات الشحن

#### `GET /carriers`

يُعيد قائمة شركات الشحن المتاحة. المنصة الوسيطة يمكنها جلبها من لمحة (`GET /api/v2/carriers`) وتخزينها مؤقتا (cache)، أو تحديدها يدويا.

**Response المتوقع:**

```json
{
  "success": true,
  "msg": "Success",
  "data": [
    {
      "carrier_id": 7,
      "name": "SMSA",
      "has_cancel": false,
      "has_cod": false,
      "has_parcel": true,
      "has_international": true
    },
    {
      "carrier_id": 10,
      "name": "Redboxsa",
      "has_cancel": true,
      "has_cod": false,
      "has_parcel": true,
      "has_international": false
    }
  ]
}
```

البلجن يقرأ `data` كمصفوفة، أو المستوى الأعلى إذا لم يوجد `data`.

---

### 4.2 المدن

#### `GET /cities/{country_code}`

مثال: `GET /cities/SA`

**Response المتوقع (صيغة لمحة):**

```json
{
  "success": true,
  "msg": "Success",
  "data": {
    "current_page": 1,
    "data": [
      { "id": 868, "name_ar": "الرياض", "name": "Riyadh" },
      { "id": 869, "name_ar": "جدة", "name": "Jeddah" }
    ],
    "per_page": 100,
    "total": 1500
  }
}
```

---

### 4.3 حساب التكلفة (Quote) -- هذا ENDPOINT خاص بالمنصة الوسيطة

#### `POST /quotes`

**هذا الـ endpoint لا يوجد في لمحة!** المنصة الوسيطة هي التي تحدد الأسعار.

**Request Body:**

```json
{
  "carrier_id": "10",
  "city": "Jeddah",
  "weight": 2.5,
  "payment_method": "cod"
}
```

**Response المتوقع:**

```json
{
  "success": true,
  "shipping_cost": 25.00,
  "currency": "SAR"
}
```

**منطق التسعير:** تحدده المنصة الوسيطة بناء على:

- شركة الشحن المختارة
- المدينة
- الوزن
- طريقة الدفع (COD قد يكون أغلى)
- يمكن أن يكون جدول أسعار ثابت أو معادلة

---

### 4.4 إنشاء طلب

#### `POST /create-order`

هذا هو الـ endpoint الأساسي. البلجن يرسل الطلب بصيغة Lamha API الكاملة. المنصة الوسيطة:

1. تحفظ الطلب في قاعدة بياناتها
2. تربطه بالمتجر (store_uuid)
3. **لاحقا** (عند تفعيل حساب لمحة): ترسله تلقائيا إلى Lamha API

**Request Body (صيغة Lamha الكاملة):**

```json
{
  "sub_total": 100,
  "discount": "10",
  "shopping_cost": 15,
  "total": 90,
  "payment_method": "cod",
  "date": "2024-07-01 22:05:00",
  "ShipmentCurrency": "SAR",
  "reference_id": "1234",
  "order_id": "5678",
  "create_shippment": true,
  "shipper": {
    "name": "متجر الأزياء",
    "phone": "+966512345678",
    "Country": "SA",
    "District": "العليا",
    "City": "Riyadh",
    "AddressLine1": "الرياض، العليا، قرب صيدلية الأزهر",
    "AddressLine2": "",
    "national_address": "RJYB2357"
  },
  "customer": {
    "name": "أحمد محمد",
    "phone1": "+966598765432",
    "phone2": "",
    "Country": "SA",
    "District": "البوادي",
    "City": "Jeddah",
    "AddressLine1": "جدة، البوادي، بجوار مخبز الحمد",
    "AddressLine2": "",
    "email": "ahmed@example.com",
    "national_address": ""
  },
  "items": [
    {
      "name": "شنطة جلد",
      "quantity": 1,
      "Sku": "BAG-001",
      "amount": "50",
      "weight": "0.5"
    },
    {
      "name": "ساعة يد",
      "quantity": 2,
      "Sku": "WATCH-002",
      "amount": "25",
      "weight": "0.3"
    }
  ],
  "coupon": "",
  "parcels": 1,
  "callback_url": "https://store-domain.com/wp-json/yamama-shipping/v1/orders/5678/status",
  "callback_pdf_url": "https://store-domain.com/wp-json/yamama-shipping/v1/orders/5678/label",
  "carrier_id": "10",
  "tap_charge_id": "1234567890"
}
```

**ملاحظات على `tap_charge_id`:**

- يُرسل مع الطلب عند الدفع عبر Tap - المنصة الوسيطة تتحقق منه عبر Tap API قبل قبول الطلب
- إذا فشل التحقق يجب رد خطأ `402 Payment Required` أو `400 Bad Request`
- المنصة تحفظ `tap_charge_id` في قاعدة بياناتها مع الطلب

**ملاحظات على الـ callback URLs:**

- `callback_url`: البلجن يُعطي رابط REST API الخاص بالمتجر لاستقبال تحديثات الحالة
- `callback_pdf_url`: لاستقبال بوليصة الشحن PDF
- المنصة الوسيطة يجب أن تحفظ هذه الروابط وتستخدمها لإرسال webhooks للمتجر

**Response المتوقع (حالة 1 - بدون شحنة فورية):**

```json
{
  "success": true,
  "msg": "The Order has been Created",
  "order_id": 79311
}
```

**Response المتوقع (حالة 2 - مع شحنة فورية):**

```json
{
  "success": true,
  "msg": "The Order has been Created",
  "order_id": 79311,
  "number_tracking": "JTE000400362038",
  "link_tracking": "https://tracking-url.com/..."
}
```

البلجن يقرأ: `order_id`, `number_tracking`, `link_tracking` من الـ response.

---

### 4.5 إعدادات الدفع

#### `GET /payment-config`

يُعيد حالة إعداد بوابة الدفع Tap. البلجن يستدعيه للتأكد من جاهزية الدفع.

**Response المتوقع:**

```json
{
  "success": true,
  "payment_gateway": "tap",
  "tap_configured": true,
  "tap_test_mode": false,
  "supported_methods": ["creditcard", "stcpay", "mada"]
}
```

---

### 4.6 Endpoints إضافية (أقل أولوية - البلجن جاهز لها)

| Endpoint | Method | الوصف |
|---|---|---|
| `GET /order/{order_id}` | GET | عرض تفاصيل طلب |
| `POST /order/cancel/{order_id}` | POST | إلغاء طلب |
| `POST /create-shipment` | POST | إنشاء شحنة لطلب موجود |
| `POST /cancel-shipment/{order_id}` | POST | إلغاء شحنة |
| `GET /label-shipment/{order_id}` | GET | جلب بوليصة الشحن PDF |

---

## 5. الـ Webhooks (من المنصة الوسيطة إلى المتجر)

عندما تتغير حالة الطلب في لمحة، لمحة ترسل webhook للمنصة الوسيطة. المنصة الوسيطة يجب أن تمرره للمتجر.

### 5.1 Webhook تحديث الحالة

**يُرسل إلى:** `{callback_url}` المحفوظ من create-order

مثال: `POST https://store-domain.com/wp-json/yamama-shipping/v1/orders/5678/status`

**Headers المطلوبة:**

```
Content-Type: application/json
X-Yamama-Signature: {HMAC-SHA256(body, store.hmac_secret)}
```

**Body:**

```json
{
  "success": true,
  "msg": "The Order has been Update Status",
  "status_id": "8",
  "order_id": "79311",
  "status_name": "تم التوصيل"
}
```

**جدول حالات لمحة:**

| الرقم | الحالة | الوصف |
|---|---|---|
| 0 | جديد | طلب جديد |
| 1 | معلق | قيد الانتظار |
| 2 | تم التنفيذ | تم تجهيز الطلب |
| 3 | جاهز للالتقاط | جاهز لشركة الشحن |
| 4 | شحنة عكسية | مرتجع |
| 5 | ملغي | تم الإلغاء |
| 6 | تم الالتقاط | شركة الشحن استلمت |
| 7 | جاري الشحن | في الطريق |
| 8 | تم التوصيل | وصل للعميل |
| 9 | فشل التوصيل | فشل |
| 10 | مرتجع | مرتجع |

### 5.2 Webhook بوليصة الشحن

**يُرسل إلى:** `{callback_pdf_url}` المحفوظ من create-order

مثال: `POST https://store-domain.com/wp-json/yamama-shipping/v1/orders/5678/label`

**Headers:** نفس أعلاه (مع HMAC signature)

**Body:**

```json
{
  "success": true,
  "pdf_url": "https://app.lamha.sa/storage/123123.pdf",
  "order_id": 79311,
  "number_tracking": "JTE000400362038",
  "link_tracking": "https://tracking-url.com/..."
}
```

---

## 6. الدفع عبر Tap

### 6.1 إعداد الدفع (Payment Config)

#### `GET /payment-config`

المنصة الوسيطة تُرجع حالة إعداد بوابة الدفع Tap. البلجن يستخدمها للتأكد من جاهزية الدفع.

**Response المتوقع:**

```json
{
  "success": true,
  "payment_gateway": "tap",
  "tap_configured": true,
  "tap_test_mode": false,
  "supported_methods": ["creditcard", "stcpay", "mada"]
}
```

### 6.2 تدفق الدفع

**التدفق:**

1. التاجر يملأ فورم الشحنة في البلجن
2. البلجن يطلب تكلفة الشحن من المنصة الوسيطة (`POST /quotes`)
3. البلجن يُنشئ فاتورة Tap باستخدام بيانات الدفع المحفوظة في WooCommerce
4. التاجر يُحوّل لصفحة دفع Tap ويدفع
5. **الدفع يذهب لحساب Tap الخاص بالمنصة**
6. بعد إتمام الدفع، البلجن يرسل `POST /create-order` مع `tap_charge_id`
7. **المنصة الوسيطة تتحقق من الدفع** عبر Tap API
8. بعد تأكيد الدفع، المنصة تحفظ الطلب وتُعيد التوجيه لاحقاً إلى لمحة

### 6.3 التحقق من الدفع (يتم في المنصة الوسيطة)

عند استقبال `POST /create-order` بحقل `tap_charge_id`:

1. المنصة تستعلم Tap (`GET /v2/charges/{tap_charge_id}`) باستخدام Bearer = platform secret
2. تتأكد أن `status` = `CAPTURED` أو `AUTHORIZED` والمبلغ يطابق تكلفة الشحن
3. إذا فشل التحقق → ترد بخطأ `400` أو `402`
4. إذا نجح → تحفظ الطلب وتكمل العملية

**ما تحتاجه المنصة الوسيطة:**

- حساب Tap بصيغة Sub-Merchant مع **Merchant ID** و **Platform Secret Key**
- المنصة الوسيطة مسؤولة عن التحقق من الدفع (verification)
- حقل `tap_charge_id` يُضاف تلقائياً لطلب `POST /create-order`

---

## 7. الربط مع Lamha API

### المعلومات المطلوبة

- **Base URL:** `https://app.lamha.sa`
- **Auth Header:** `X-LAMHA-TOKEN: Lamha_xxxxxxxxxxxx`
- **Rate Limit:** 60 طلب/دقيقة

### Endpoints الأساسية في لمحة

| Endpoint | Method | الوصف |
|---|---|---|
| `/api/v2/carriers` | GET | قائمة شركات الشحن |
| `/api/v2/cities/{country}` | GET | قائمة المدن |
| `/api/v2/create-order` | POST | إنشاء طلب (نفس الصيغة أعلاه) |
| `/api/v2/order/{order_id}` | GET | عرض طلب |
| `/api/v2/order/{order_id}` | PUT | تعديل طلب |
| `/api/v2/order/{order_id}` | DELETE | حذف طلب |
| `/api/v2/order/cancel/{order_id}` | POST | إلغاء طلب |
| `/api/v2/order/return/{order_id}` | POST | استرجاع طلب |
| `/api/v2/create-shipment` | POST | إنشاء شحنة |
| `/api/v2/cancel-shipment/{order_id}` | POST | إلغاء شحنة |
| `/api/v2/label-shipment/{order_id}` | GET | بوليصة شحن PDF |
| `/api/v2/carrier/city-cover` | POST | تغطية شركات الشحن لمدينة |
| `/api/v2/warehouses` | GET | قائمة المستودعات |

### تدفق إعادة التوجيه لـ Lamha

```
1. المنصة الوسيطة تستقبل POST /create-order من البلجن
2. تحفظ الطلب في قاعدة بياناتها (status: pending_lamha)
3. تعدّل callback_url و callback_pdf_url ليشيرا للمنصة الوسيطة نفسها
4. ترسل POST /api/v2/create-order إلى app.lamha.sa مع X-LAMHA-TOKEN
5. تحفظ lamha_order_id في قاعدة بياناتها
6. ترد للبلجن بنفس الاستجابة

عند استقبال webhook من لمحة:
7. المنصة تستقبل webhook على callback_url الخاص بها
8. تحدّث حالة الطلب في قاعدة بياناتها
9. تُعيد توجيه webhook للمتجر على callback_url الأصلي (مع HMAC signature)
```

---

## 8. قاعدة البيانات (Schema المقترح)

### جدول المتاجر (stores)

```
id, store_uuid (unique), site_url, admin_url, store_name, admin_email,
currency, country_code, city_name, api_token, hmac_secret,
lamha_token (nullable), is_active, created_at, updated_at
```

### جدول الطلبات (orders)

```
id, store_id (FK), wc_order_id, lamha_order_id (nullable),
reference_id, carrier_id, payment_method, total, shipping_cost,
status (lamha status_id), callback_url, callback_pdf_url,
payload_json (full Lamha payload), tracking_number, tracking_link,
label_url, tap_charge_id, sent_to_lamha (boolean),
sent_to_lamha_at (nullable), created_at, updated_at
```

### جدول التسعير (pricing)

```
id, carrier_id, city_pattern, min_weight, max_weight,
base_price, per_kg_price, cod_surcharge, is_active,
created_at, updated_at
```

---

## 9. طرق الدفع المدعومة في لمحة

| الطريقة | القيمة |
|---|---|
| الدفع عند الاستلام | `cod` |
| مدفوع أونلاين | `paid` |
| بنك | `bank` |
| مدى | `mada` |
| بطاقة ائتمانية | `credit_card` |
| Tap | `paid` |
| تمارا (تقسيط) | `tamara_installment` |
| Stripe | `stripe` |
| Tabby | `tabby` |
| STC Pay | `stc_pay` |

---

## 10. ملخص الأولويات

### المرحلة 1 (أساسي)

1. `POST /register` - تسجيل المتاجر
2. `GET /payment-config` - إرجاع حالة إعداد بوابة الدفع Tap
3. `GET /carriers` - قائمة شركات الشحن (يمكن بيانات ثابتة مبدئيا)
4. `POST /quotes` - تسعير الشحنات (جدول أسعار بسيط)
5. `POST /create-order` - استقبال وحفظ الطلبات (+ التحقق من `tap_charge_id`)
6. Webhook forwarding - تمرير تحديثات الحالة للمتاجر

### المرحلة 2 (ربط لمحة)

6. إعادة توجيه الطلبات تلقائيا إلى Lamha API
7. استقبال webhooks من لمحة وتمريرها
8. `GET /cities/{country}` - جلب المدن من لمحة

### المرحلة 3 (متقدم)

9. لوحة تحكم للمنصة الوسيطة (إدارة المتاجر، الأسعار، الطلبات)
10. Endpoints إضافية (cancel, return, label)
