<?php

require_once __DIR__."/../../config/database.php";
require_once __DIR__."/../../core/Auth.php";
require_once __DIR__."/../../core/Response.php";
require_once __DIR__."/../wallet/WalletController.php";

class PurchaseController {

    private $db;

    public function __construct(){
        $this->db=Database::getConnection();
    }

    /* =============================
       BUY FULL BOOK
    ============================= */

    public function buyUploadedBook(){

        $user=Auth::check();

        $data=json_decode(file_get_contents("php://input"),true);
        $book_id=$data["book_id"]??null;

        if(!$book_id){
            Response::error("Book ID missing");
        }

        /* =============================
           GET BOOK
        ============================= */

        $stmt=$this->db->prepare("
            SELECT id,price,user_id
            FROM books
            WHERE id=?
        ");
        $stmt->execute([$book_id]);
        $book=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$book){
            Response::error("Book not found");
        }

        /* =============================
           OWNER FREE ACCESS
        ============================= */

        if($book["user_id"]==$user["id"]){
            Response::success("Owner access granted");
            return;
        }

        /* =============================
           ALREADY BOUGHT
        ============================= */

        $check=$this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id=? AND book_id=? AND chapter_id IS NULL
        ");
        $check->execute([$user["id"],$book_id]);

        if($check->rowCount()>0){
            Response::success("Already purchased");
            return;
        }

        /* =============================
           CHECK WALLET BALANCE (LEDGER)
        ============================= */

        $balStmt=$this->db->prepare("
            SELECT IFNULL(SUM(
                CASE WHEN type='credit' THEN amount ELSE -amount END
            ),0) AS balance
            FROM wallet_transactions
            WHERE user_id=?
        ");
        $balStmt->execute([$user["id"]]);
        $bal=$balStmt->fetch(PDO::FETCH_ASSOC)["balance"];

        if($bal < $book["price"]){
            Response::error("Insufficient wallet balance");
        }

        /* =============================
           TRANSACTION: DEDUCT + PURCHASE
        ============================= */

        try{

            $this->db->beginTransaction();

            /* DEBIT WALLET */

            $this->db->prepare("
                INSERT INTO wallet_transactions
                (user_id,type,amount,reference)
                VALUES(?, 'debit', ?, ?)
            ")->execute([
                $user["id"],
                $book["price"],
                "book_purchase_".$book_id
            ]);

            /* SAVE PURCHASE */

            $this->db->prepare("
                INSERT INTO purchases(user_id,book_id,chapter_id,amount)
                VALUES(?,?,NULL,?)
            ")->execute([
                $user["id"],
                $book_id,
                $book["price"]
            ]);

            $this->db->commit();

            Response::success("Book purchased successfully");

        }catch(Exception $e){

            $this->db->rollBack();
            Response::serverError("Purchase failed");

        }
    }

}