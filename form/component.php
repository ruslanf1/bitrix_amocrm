<?
use Bitrix\Sale;
Bitrix\Main\Loader::includeModule("sale");
include $_SERVER['DOCUMENT_ROOT'] . '/amo_crm_controllers/AmoCrmController.php';

$siteId = Bitrix\Main\Context::getCurrent()->getSite();
$fuser = Sale\Fuser::getId();
$basket = Sale\Basket::loadItemsForFUser($fuser, $siteId);
$basketItems = $basket->getListOfFormatText();

try {
    if ($_POST['form_text_1'] && $_POST['form_text_2'] && $_POST['form_text_3'] && $_POST['form_email_4'] &&
        $_POST['form_text_5'] && $_POST['form_text_6'] && $_POST['form_text_7'] && $_POST['form_text_8'])
    {
        $data = new AmoCrmController();
        $data->get([
            "FORM" => "Форма заказа",
            "COMPANY_NAME" => $_POST['form_text_1'],
            "CONTACT_NAME" => $_POST['form_text_2'],
            "PHONE" => $_POST['form_text_3'],
            "EMAIL" => $_POST['form_email_4'],
            "CITY" => $_POST['form_text_5'],
            "ADDRESS" => $_POST['form_text_6'],
            "POST_INDEX" => $_POST['form_text_7'],
            "COMPANY_DETAILS" => $_POST['form_text_8'],
            "COMMENT" => $_POST['form_text_10'],
            "BASKET_ITEM" => $basketItems,
        ]);
    }
} catch (Exception $e) {}