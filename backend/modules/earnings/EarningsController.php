<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../core/Response.php";
require_once __DIR__ . "/../../core/Auth.php";

class EarningsController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /* =========================
       DASHBOARD SUMMARY + SALES
    ========================= */
    public function index(){

        $user = Auth::check();
        $uid = $user["id"];

        /* TOTAL WALLET BALANCE */

        $bal = $this->db->prepare("
            SELECT IFNULL(SUM(
                CASE WHEN type='credit' THEN amount ELSE -amount END
            ),0) AS balance
            FROM wallet_transactions
            WHERE user_id=?
        ");
        $bal->execute([$uid]);
        $total_balance = (float)$bal->fetch()["balance"];


        /* TODAY EARNINGS (BOOK SALES ONLY) */

        $today = $this->db->prepare("
            SELECT IFNULL(SUM(amount),0) AS total
            FROM wallet_transactions
            WHERE user_id=?
            AND type='credit'
            AND reference LIKE '%sale%'
            AND DATE(created_at)=CURDATE()
        ");
        $today->execute([$uid]);
        $today_total=(float)$today->fetch()["total"];


        /* MONTH EARNINGS */

        $month = $this->db->prepare("
            SELECT IFNULL(SUM(amount),0) AS total
            FROM wallet_transactions
            WHERE user_id=?
            AND type='credit'
            AND reference LIKE '%sale%'
            AND YEAR(created_at)=YEAR(CURDATE())
            AND MONTH(created_at)=MONTH(CURDATE())
        ");
        $month->execute([$uid]);
        $month_total=(float)$month->fetch()["total"];


        /* SALES HISTORY */

        $sales = $this->db->prepare("
            SELECT amount,reference,created_at
            FROM wallet_transactions
            WHERE user_id=?
            AND type='credit'
            AND reference LIKE '%sale%'
            ORDER BY id DESC
            LIMIT 100
        ");
        $sales->execute([$uid]);

        $rows=$sales->fetchAll(PDO::FETCH_ASSOC);

        $formatted=[];

        foreach($rows as $r){

            $formatted[]=[
                "book_title"=>"Book Sale",
                "buyer"=>"User Purchase",
                "amount"=>$r["amount"],
                "date"=>$r["created_at"],
                "status"=>"success"
            ];
        }

        Response::success("Earnings overview",[
            "total_balance"=>$total_balance,
            "today"=>$today_total,
            "month"=>$month_total,
            "total_sales"=>count($formatted),
            "sales"=>$formatted
        ]);
    }


    /* =========================
       WITHDRAW MONEY
    ========================= */
    public function withdraw(){

        $user = Auth::check();
        $uid=$user["id"];

        $data=json_decode(file_get_contents("php://input"),true);
        $amount=floatval($data["amount"]??0);

        if($amount<=0){
            Response::error("Invalid amount");
        }

        /* CHECK BALANCE */

        $bal=$this->db->prepare("
            SELECT IFNULL(SUM(
                CASE WHEN type='credit' THEN amount ELSE -amount END
            ),0) AS balance
            FROM wallet_transactions
            WHERE user_id=?
        ");
        $bal->execute([$uid]);
        $balance=$bal->fetch()["balance"];

        if($balance < $amount){
            Response::error("Insufficient wallet balance");
        }

        /* CHECK BANK */

        $bank=$this->db->prepare("
            SELECT id FROM bank_details WHERE user_id=?
        ");
        $bank->execute([$uid]);

        if($bank->rowCount()==0){
            Response::error("Please add bank details first");
        }

        /* CREATE WITHDRAW DEBIT */

        $this->db->prepare("
            INSERT INTO wallet_transactions
            (user_id,type,amount,reference)
            VALUES(?, 'debit', ?, 'withdraw_request')
        ")->execute([$uid,$amount]);

        Response::success("Withdrawal request submitted");
    }

}