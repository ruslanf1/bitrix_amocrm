<?php
$GLOBALS['server'] = include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php';
$GLOBALS['amo'] = include $_SERVER['DOCUMENT_ROOT'] . '/amo_crm_controllers/amo_settings.php';

class AmoCrmController
{
    public function get($array) {
        $this->amo($array);
    }

    private function amo($array) {
        try {
            $contactId = $this->searchContact($array['PHONE'], $array['EMAIL']);

            if (!$contactId) {
                $data = [
                    [
                        'name' => $array['FORM'],
                        'status_id' => '',
                        'pipeline_id' => '',
                        '_embedded' => [
                            'contacts' => [
                                0 => [
                                    'name' => $array['CONTACT_NAME'],
                                    'custom_fields_values' => [
                                        0 => [
                                            'field_code' => 'PHONE',
                                            'values' => [
                                                0 => [
                                                    'value' => $array['PHONE']
                                                ]
                                            ]
                                        ],
                                        1 => [
                                            'field_code' => 'EMAIL',
                                            'values' => [
                                                0 => [
                                                    'value' => $array['EMAIL']
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]

                    ]
                ];
                $lead = $this->curl($data, 'api/v4/leads/complex', 'POST', 'token');
                $leadId = $lead[0]->id;
            } else {
                $data = [
                    [
                        'name' => $array['FORM'],
                        'status_id' => '',
                        'pipeline_id' => '',
                        '_embedded' => [
                            'contacts' => [
                                0 => [
                                    'id' => $contactId
                                ]
                            ]
                        ]
                    ]
                ];
                $lead = $this->curl($data, 'api/v4/leads', 'POST', 'token');
                $leadId = $lead->_embedded->leads[0]->id;
            }

            if ($array['FORM'] == 'Форма обратной связи') {
                $note = [
                    [
                        'entity_id' => $leadId,
                        'note_type' => 'common',
                        'params' => [
                            'text' =>
                                'Населенный пункт: ' . $array['CITY'] . '.'
                        ]
                    ]
                ];
            }
            if ($array['FORM'] == 'Форма заказа') {
                $note = [
                    [
                        'entity_id' => $leadId,
                        'note_type' => 'common',
                        'params' => [
                            'text' =>
                                "Название компании: {$array['COMPANY_NAME']}.\n" .
                                "Населенный пункт: {$array['CITY']}.\n" .
                                "Адрес доставки: {$array['ADDRESS']}.\n" .
                                "Почтовый индекс: {$array['POST_INDEX']}.\n" .
                                "Реквизиты компании: {$array['COMPANY_DETAILS']}.\n" .
                                "Товары: " . str_replace('&nbsp;', '', implode(', ', $array['BASKET_ITEM'])) . ".\n" .
                                ($array['COMMENT'] ? "Комментарии: " . $array['COMMENT'] . "." : '')
                        ]
                    ]
                ];
            }
            $this->curl($note, 'api/v4/leads/notes', 'POST', 'token');

        } catch (Exception $e) {
            return true;
        } finally {
            return true;
        }
    }

    public function saveToken() {
        try {
            $array = [
                'client_id' => $GLOBALS['amo']['client_id'],
                'client_secret' => $GLOBALS['amo']['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $GLOBALS['amo']['code'],
                'redirect_uri' => $GLOBALS['amo']['redirect_uri'],
            ];
            $data = $this->curl($array, 'oauth2/access_token', 'POST', 'auth');
            $this->storeDb($data, 'insert');

        } catch (Exception $e) {
        } finally {}
    }

    private function editToken($refresh_token) {
        try {
            $array = [
                'client_id' => $GLOBALS['amo']['client_id'],
                'client_secret' => $GLOBALS['amo']['client_secret'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'redirect_uri' => $GLOBALS['amo']['redirect_uri'],
            ];
            $data = $this->curl($array, 'oauth2/access_token', 'POST', 'auth');
            $this->storeDb($data, 'update');

            return $data->access_token;

        } catch (Exception $e) {
        } finally {}
    }

    private function getToken() {
        try {
            $db = $this->getDb();

            if (isset($db[0][3]) && $db[0][3] <= time()) {
                return $this->editToken($db[0][2]);
            } else {
                return $db[0][1];
            }
        } catch (Exception $e) {
        } finally {}
    }

    private function getDb() {
        try {
            $link = $this->connectDb();

            if ($link == false) {
                throw new Exception;
            }
            $sql = "SELECT * FROM `amo_crm_access`";
            $result = mysqli_query($link, $sql);

            return mysqli_fetch_all($result);

        } catch (Exception $e) {
        } finally {}
    }

    private function storeDb($data, $workDb) {
        try {
            $link = $this->connectDb();

            if ($link == false) {
                throw new Exception;
            }
            $data->access_token ? $expiresIn = time() + $data->expires_in : $expiresIn = null;

            if ($workDb == 'insert') {
                $sql = "INSERT INTO `amo_crm_access` (`access_token`, `refresh_token`, `expires_in`) VALUES ('$data->access_token', '$data->refresh_token', '$expiresIn')";
            }
            if ($workDb == 'update') {
                $sql = "UPDATE `amo_crm_access` SET `access_token` = '$data->access_token', `refresh_token` = '$data->refresh_token', `expires_in` = '$expiresIn' WHERE `id` = 1";
            }
            mysqli_query($link, $sql);

        } catch (Exception $e) {
        } finally {}
    }

    private function connectDb() {
        try {

            return mysqli_connect(
                $GLOBALS['server']['connections']['value']['default']['host'],
                $GLOBALS['server']['connections']['value']['default']['login'],
                $GLOBALS['server']['connections']['value']['default']['password'],
                $GLOBALS['server']['connections']['value']['default']['database']
            );
        } catch (Exception $e) {
        } finally {}
    }

    private function searchContact($number, $email) {
        try {
            if ($number) {
                $contactResponse = $this->curl($number,'api/v4/contacts?query=' . $number, 'GET', 'token');
                if ($contactResponse) {
                    $contactId = $contactResponse->_embedded->contacts[0]->id;
                    if ($contactId) {
                        return $contactId;
                    }
                } else {
                    return false;
                }
            }
            if ($email) {
                $contactResponse = $this->curl($email,'api/v4/contacts?query=' . $email, 'GET', 'token');
                if ($contactResponse) {
                    $contactId = $contactResponse->_embedded->contacts[0]->id;
                    if ($contactId) {
                        return $contactId;
                    }
                } else {
                    return false;
                }
            }
        } catch (Exception $e) {
        } finally {}
    }

    private function curl($data, $link, $method, $header) {
        try {
            $subdomain = $GLOBALS['amo']['subdomain'];
            $link = 'https://' . $subdomain . '.amocrm.ru/' . $link;

            if ($header == 'token') {
                $headers = ['Authorization: Bearer ' . $this->getToken()];
            }
            if ($header == 'auth') {
                $headers = ['Content-Type:application/json'];
            }

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
            curl_setopt($curl, CURLOPT_URL, $link);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            $out = curl_exec($curl);
            curl_close($curl);

            return json_decode($out);

        } catch (Exception $e) {
        } finally {}
    }
}
