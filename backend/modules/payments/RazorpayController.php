<?php
require_once __DIR__."/../../config/database.php";
require_once __DIR__."/../../core/Response.php";
require_once __DIR__."/../../core/Auth.php";

class RazorpayController{

private $db;
private $key_id="rzp_test_xxxxxxxx";
private $key_secret="xxxxxxxxxxxx";

public function __construct(){
$this->db=Database::getConnection();
}


/* ================= CREATE ORDER ================= */

public function createOrder(){

$user=Auth::check();

$data=json_decode(file_get_contents("php://input"),true);
$book_id=$data["book_id"]??null;

if(!$book_id) Response::error("Book missing");

/* GET PRICE FROM DB */

$stmt=$this->db->prepare("SELECT price FROM books WHERE id=?");
$stmt->execute([$book_id]);
$book=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$book) Response::error("Book not found");

$amount=$book["price"];

$orderData=[
"amount"=>$amount*100,
"currency"=>"INR",
"receipt"=>"order_".time()
];

$ch=curl_init("https://api.razorpay.com/v1/orders");

curl_setopt($ch,CURLOPT_USERPWD,$this->key_id.":".$this->key_secret);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($orderData));
curl_setopt($ch,CURLOPT_HTTPHEADER,["Content-Type: application/json"]);

$response=curl_exec($ch);
curl_close($ch);

$order=json_decode($response,true);

if(!isset($order["id"])) Response::error("Order failed");

/* SAVE PAYMENT RECORD */

$this->db->prepare("
INSERT INTO payments(user_id,book_id,order_id,amount,status)
VALUES(?,?,?,?, 'created')
")->execute([$user["id"],$book_id,$order["id"],$amount]);

$order["key_id"]=$this->key_id;

Response::success("Order created",$order);
}



/* ================= VERIFY PAYMENT ================= */

public function verifyPayment(){

$user=Auth::check();

$data=json_decode(file_get_contents("php://input"),true);

$order_id=$data["razorpay_order_id"];
$payment_id=$data["razorpay_payment_id"];
$sig=$data["razorpay_signature"];
$book_id=$data["book_id"];

/* VERIFY SIGNATURE */

$generated=hash_hmac(
"sha256",
$order_id."|".$payment_id,
$this->key_secret
);

if($generated!==$sig){
Response::error("Invalid signature");
}

/* CHECK BOOK */

$stmt=$this->db->prepare("
SELECT price,user_id AS author_id FROM books WHERE id=?
");
$stmt->execute([$book_id]);
$book=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$book) Response::error("Book not found");

/* PREVENT DUPLICATE PURCHASE */

$chk=$this->db->prepare("
SELECT id FROM purchases WHERE user_id=? AND book_id=?
");
$chk->execute([$user["id"],$book_id]);

if($chk->rowCount()>0){
Response::success("Already purchased");
return;
}

/* TRANSACTION */

try{

$this->db->beginTransaction();

/* UPDATE PAYMENT */

$this->db->prepare("
UPDATE payments SET payment_id=?,status='success'
WHERE order_id=?
")->execute([$payment_id,$order_id]);

/* INSERT PURCHASE */

$this->db->prepare("
INSERT INTO purchases(user_id,book_id)
VALUES(?,?)
")->execute([$user["id"],$book_id]);

/* CREDIT AUTHOR (LEDGER WALLET) */

$this->db->prepare("
INSERT INTO wallet_transactions(user_id,type,amount,reference)
VALUES(?, 'credit', ?, ?)
")->execute([
$book["author_id"],
$book["price"],
"razorpay_sale_".$book_id
]);

$this->db->commit();

Response::success("Payment verified");

}catch(Exception $e){

$this->db->rollBack();
Response::serverError("Payment failed");

}

}



/* ================= FAKE PAYMENT (FOR TESTING) ================= */

public function fakePayment(){

$user = Auth::check();

$data=json_decode(file_get_contents("php://input"),true);
$book_id=$data["book_id"]??null;

if(!$book_id) Response::error("Book missing");

/* GET BOOK */

$stmt=$this->db->prepare("
SELECT price,user_id AS author_id FROM books WHERE id=?
");
$stmt->execute([$book_id]);
$book=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$book) Response::error("Book not found");

/* OWNER FREE */

if($book["author_id"]==$user["id"]){
Response::success("Owner access");
return;
}

/* ALREADY PURCHASED */

$chk=$this->db->prepare("
SELECT id FROM purchases WHERE user_id=? AND book_id=?
");
$chk->execute([$user["id"],$book_id]);

if($chk->rowCount()>0){
Response::success("Already purchased");
return;
}

try{

$this->db->beginTransaction();

/* SAVE PAYMENT */

$this->db->prepare("
INSERT INTO payments(user_id,book_id,order_id,amount,status)
VALUES(?,?,?,?, 'success')
")->execute([
$user["id"],
$book_id,
"FAKE_".time(),
$book["price"]
]);

/* SAVE PURCHASE */

$this->db->prepare("
INSERT INTO purchases(user_id,book_id)
VALUES(?,?)
")->execute([$user["id"],$book_id]);

/* CREDIT AUTHOR */

$this->db->prepare("
INSERT INTO wallet_transactions(user_id,type,amount,reference)
VALUES(?, 'credit', ?, 'fake_book_sale')
")->execute([$book["author_id"],$book["price"]]);

$this->db->commit();

Response::success("Fake payment success");

}catch(Exception $e){

$this->db->rollBack();
Response::serverError("Payment failed");

}

}

}