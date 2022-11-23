<?php
include $_SERVER['DOCUMENT_ROOT'] . '/amo_crm_controllers/AmoCrmController.php';

try {
    if ($_POST["user_name"] && $_POST["user_email"] && $_POST["tel"] && $_POST["city"]) {
        $data = new AmoCrmController();
        $data->get([
            "FORM" => "Форма обратной связи",
            "CONTACT_NAME" => $_POST["user_name"],
            "EMAIL" => $_POST["user_email"],
            "PHONE" => $_POST["tel"],
            "CITY" => $_POST["city"],
        ]);
    }
} catch (Exception $e) {}