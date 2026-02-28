<?php

require_once 'pecunia.civix.php';

/**
 * Implements hook_civicrm_pre().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */

/**
 * Implements hook_civicrm_pre().
 *
 * Deze hook wordt aangeroepen VOORDAT de data in de database wordt opgeslagen.
 * Ideaal voor het manipuleren van bedragen (zoals negatief maken) of opschonen van velden.
 */
function pecunia_civicrm_pre($op, $objectName, $id, &$params) {

    // 1. RECURSIE BEVEILIGING
    // Voorkomt dat deze functie zichzelf oneindig blijft aanroepen als er binnen de hook 
    // acties plaatsvinden die opnieuw een 'pre' triggeren.
    static $processing_pecunia_pre = FALSE;
    if ($processing_pecunia_pre) return;

    $extdebug = 3;  // Debug niveau: 1 = basic // 4 = verbose (params)

    // Alleen verwerken als het om een Bijdrage (Contribution) gaat
    if ($objectName === 'Contribution') {

        // LOGGING: Start van de pre-hook met alle inkomende parameters
        wachthond($extdebug, 4, "###########################################################", "");
        wachthond($extdebug, 4, 'PECUNIA pre - objectName',              $objectName);
        wachthond($extdebug, 4, 'PECUNIA pre - op',                      $op);
        wachthond($extdebug, 4, 'PECUNIA pre - contrib_id',              $id);
        wachthond($extdebug, 4, 'PECUNIA pre - params',                  $params);
        wachthond($extdebug, 4, "###########################################################", "");

        // 2. FILTER OP FINANCIAL TYPE
        // We verwerken alleen specifieke types (Kampgelden & Declaraties). 
        // We gebruiken (int) om type-mismatches in PHP te voorkomen.
        $financial_type_id = isset($params['financial_type_id']) ? (int)$params['financial_type_id'] : 0;
        
        if (in_array($financial_type_id, [1, 4, 14, 15, 22])) {
            
            // Zet de recursie-beveiliging op TRUE tijdens de verwerking
            $processing_pecunia_pre = TRUE;

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - [type: $objectName] [contrib_id: $id]",        "[START]");
            wachthond($extdebug, 1, "#########################################################################");

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - MAAK VAN FAILD PENDING",                      "[FAILED]");
            wachthond($extdebug, 1, "#########################################################################");

            // Als een betaling mislukt is (status 4), zetten we deze terug naar Pending (2).
            // Dit is nodig om de gebruiker of admin de kans te geven de betaling opnieuw te proberen.
            if (isset($params['contribution_status_id']) && (int)$params['contribution_status_id'] === 4) {
                wachthond($extdebug, 1, "### PECUNIA PRE - VERANDER STATUS FAILED (4) NAAR PENDING (2)", "[contrib_id: $id]");
                $params['contribution_status_id'] = 2;
                $params['cancel_reason'] = 'was failed (automatically reset by Pecunia)';
            } 

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - ONTVANGSTDATUM CHECK",                         "[DATUM]");
            wachthond($extdebug, 1, "#########################################################################");

            // CiviCRM heeft soms een geldige receive_date nodig voor boekhoudkundige rapportages.
            if (empty($params['receive_date'])) {
                wachthond($extdebug, 1, "### PECUNIA PRE - ZET RECEIVE DATE OP NOW IF EMPTY", "[contrib_id: $id]");
                $params['receive_date'] = date('Y-m-d H:i:s'); // ISO formaat is veiliger dan YmdHis
            }

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - DECLARATIE LOGICA",                       "[DECLARATIE]");
            wachthond($extdebug, 1, "#########################################################################");

            // Declaraties moeten als een negatief bedrag in het systeem staan omdat het uitgaven zijn.
            if ($financial_type_id === 22) {
                wachthond($extdebug, 1, "### PECUNIA PRE 1.1 VERANDER DECLARATIEBEDRAG NAAR NEGATIEF");

                // Gebruik de inkomende total_amount. We gaan uit van de waarde in $params.
                $currentAmount = $params['total_amount'] ?? 0;

                if ($currentAmount > 0) {
                    $negativeAmount = abs($currentAmount) * -1;

                    // Overschrijf de bedragen in de $params array zodat ze correct in de DB komen
                    $params['total_amount'] = $negativeAmount;
                    $params['net_amount']   = $negativeAmount;
                    $params['fee_amount']   = 0;
                    
                    // Forceer status naar 'Pending Refund' (9) voor een zuivere administratie
                    $params['contribution_status_id'] = 9; 

                    wachthond($extdebug, 1, "CHANGED TOTAL AMOUNT VALUE TO NEGATIVE", $params['total_amount']);
                }
            }

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - OPSCHONEN IBAN",                                "[IBAN]");
            wachthond($extdebug, 1, "#########################################################################");

            // Spaties uit het IBAN veld verwijderen om validatieproblemen bij exports te voorkomen.
            if (!empty($params['custom_576_775'])) {
                $params['custom_576_775'] = strtoupper(str_replace(' ', '', $params['custom_576_775']));
                wachthond($extdebug, 4, 'PECUNIA: IBAN opgeschoond', $params['custom_576_775']);
            }
/*
            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA POST 5.0 - WHATSAPP TEST",                           "[WAPP]");
            wachthond($extdebug, 1, "#########################################################################");

            try {

                $result = civicrm_api4('Whatsapp', 'send', [
                    'language'  => 'nl_NL',
                    'providerID'=> 1,
                    'contactID' => 27, // Use a valid contact ID
                    'to'        => '+31648942320', // Use a test number
                    'payload'   => 'Test message from CiviCRM 1',
                ]);

                // Als we hier komen, is de aanroep technisch geslaagd
                wachthond($extdebug, 4, 'WhatsApp: Bericht succesvol verzonden', 'Contact: 27 naar +31648942320');
                
                print_r($result);

            } catch (\Exception $e) {
                // Bij een fout (bijv. ongeldige API key of provider offline) wordt dit uitgevoerd
                wachthond($extdebug, 3, 'WhatsApp FOUT: Verzenden mislukt', $e->getMessage());
                
                echo "Foutmelding: " . $e->getMessage();
            }

            wachthond($extdebug, 1, "#########################################################################");
            wachthond($extdebug, 1, "### PECUNIA PRE - [type: $objectName] [contrib_id: $id]",        "[EINDE]");
            wachthond($extdebug, 1, "#########################################################################");
*/
            // Zet de recursie-beveiliging weer uit
            $processing_pecunia_pre = FALSE;
        }

        wachthond($extdebug,3, 'FINAL CONTRIBUTION PRE PARAMS', $params);
    }

}

function pecunia_civicrm_post($op, $objectName, $objectId, &$objectRef) {

    $extdebug = 3; // Zet op 1 voor basic logging, 3 voor params

    // 1. Basisvalidatie
    if ($objectName !== 'Contribution' || !in_array($op, ['create', 'edit'])) {
        return;
    }

    // 2. RECURSIE BEVEILIGING
    static $processing_pecunia = FALSE;
    if ($processing_pecunia) return;

    // 3. Haal data veilig op (VOORKOMT DE TYPERROR)
    // We gebruiken nu $contact_id zoals gevraagd
    $contact_id = $objectRef->contact_id ?? NULL;
    $financial_type_id = $objectRef->financial_type_id ?? NULL;

        // CRUCIALE CHECK: Stop als contact_id leeg of null is
    if (empty($contact_id)) {
        return; 
    }

    // 4. Filter op specifieke types
    if (!in_array((int)$financial_type_id, [1, 4, 14, 15, 22])) {
        return;
    }

    // Zet de beveiliging aan voor de rest van de verwerking
    $processing_pecunia = TRUE;

    wachthond($extdebug,3, "###########################################################", "");
    wachthond($extdebug,3, 'PECUNIA post - op',                      $op);
    wachthond($extdebug,3, 'PECUNIA post - objectName',              $objectName);
    wachthond($extdebug,3, 'PECUNIA post - objectId',                $objectId);
    wachthond($extdebug,3, 'PECUNIA post - objectRef',               $objectRef);
    wachthond($extdebug,3, "###########################################################", "");

    // START TIMER VOOR TOTALE DOORLOOP
    watchdog('civicrm_timing', core_microtimer("START PECUNIA POST voor contrib_id: $objectId"), NULL, WATCHDOG_DEBUG);

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 1.0 - BEREKEN DATA, STATUS & CAMPAGNE INFO",    "[START]");
    wachthond($extdebug, 1, "#########################################################################");

    // 3. Haal HUIDIGE data op uit de database
    $params_get_contribution = [
        'checkPermissions' => FALSE,
        'select' => [
            'total_amount', 
            'net_amount',
            'paid_amount', 
            'balance_amount', 
            'financial_type_id', 
            'contribution_status_id', 
            'receive_date', 
            'CONT_KAMPGELD.*'
        ],
        'where' => [['id', '=', $objectId]],
    ];

    watchdog('civicrm_timing', core_microtimer("START Get Contribution Data (APIv4)"), NULL, WATCHDOG_DEBUG);
    $result_get_contribution = civicrm_api4('Contribution', 'get', $params_get_contribution)->first();
    watchdog('civicrm_timing', core_microtimer("EINDE Get Contribution Data (APIv4)"), NULL, WATCHDOG_DEBUG);

    // Check of ophalen gelukt is en type nog steeds klopt
    if (!$result_get_contribution || !in_array((int)$result_get_contribution['financial_type_id'], [1, 4, 14, 15, 22])) {
        $processing_pecunia = FALSE; // Zorg dat deze naam matcht met je static variabele!
        return;
    }

    // Variabelen uit het resultaat halen
    $bedrag     = $result_get_contribution['total_amount'];
    $betaald    = $result_get_contribution['paid_amount'];
    $balans     = $result_get_contribution['balance_amount'];
    $status_id  = $result_get_contribution['contribution_status_id'];
    $p_id       = NULL;

    // 4. LineItem Logica (Koppeling met deelnemer)
    $params_lineitem = [
        'checkPermissions' => FALSE,
        'select' => [
            'entity_id',
            'entity_id.contact_id.first_name',
            'entity_id.event_id',
            'entity_id.event_id.title',
            'entity_id.status_id',
            'entity_id.status_id:label',
            'price_field_id:label',
            'label',
            'PART_KAMPGELD.regeling',
        ],
        'where' => [
            ['contribution_id', '=', $objectId],
            ['entity_table',    '=', 'civicrm_participant'],
        ],
    ];

    watchdog('civicrm_timing', core_microtimer("START Get LineItems (APIv4)"), NULL, WATCHDOG_DEBUG);
    $result_lineitem = civicrm_api4('LineItem', 'get', $params_lineitem);
    watchdog('civicrm_timing', core_microtimer("EINDE Get LineItems (APIv4)"), NULL, WATCHDOG_DEBUG);

    if ($result_lineitem->count() > 0) {
        foreach ($result_lineitem as $line) {
            $p_id       = $line['entity_id'];
            $e_id       = $line['entity_id.event_id'];
            $e_title    = $line['entity_id.event_id.title'];
            $s_label    = $line['entity_id.status_id:label'];
            $first_name = $line['entity_id.contact_id.first_name'] ?? 'Onbekend';
            
            wachthond($extdebug, 3, "PECUNIA - LineItem", "P-ID: $p_id | Event: $e_title | Status: $s_label");
        }
    } else {
        wachthond($extdebug, 3, "PECUNIA - Geen LineItem koppeling gevonden.");
    }

    // A. INITIALISATIE: Zet alles standaard op NULL om foutmeldingen verderop te voorkomen
    $ditevent_part_contact_id        = NULL;
    $ditevent_part_eventid           = NULL;
    $ditevent_register_date          = NULL;
    $ditevent_part_kampgeld_regeling = NULL;
    
    // Event variabelen ook initialiseren!
    $eventkamp_event_id              = NULL;
    $eventkamp_kampnaam              = NULL;
    $eventkamp_kampkort              = NULL;
    $eventkamp_kampkort_cap          = NULL;
    $eventkamp_event_start           = NULL;
    $eventkamp_kampjaar              = NULL;
    $eventkamp_kamptype_id           = NULL;
    $eventkamp_kamptype_naam         = NULL;
    $eventkamp_event_weeknr          = NULL;

    // B. LOGICA: Alleen uitvoeren als er daadwerkelijk een Deelnemer (LineItem) is
    if (!empty($p_id)) {
        // 1. Haal Deelnemer info op
        $array_partditevent              = base_pid2part($p_id);
        
        $ditevent_part_contact_id        = $array_partditevent['contact_id']             ?? NULL;
        $ditevent_part_eventid           = $array_partditevent['event_id']               ?? NULL;
        $ditevent_register_date          = $array_partditevent['register_date']          ?? NULL;
        $ditevent_part_kampgeld_regeling = $array_partditevent['part_kampgeld_regeling'] ?? NULL;

        // 2. Haal Event info op (NU HIER BINNEN GEZET)
        // We doen dit alleen als we een event_id hebben gevonden
        if (!empty($ditevent_part_eventid)) {
            $array_eventinfo                 = base_eid2event($ditevent_part_eventid, $p_id);
            
            $eventkamp_event_id              = $array_eventinfo['eventkamp_event_id']        ?? NULL;
            $eventkamp_kampnaam              = $array_eventinfo['eventkamp_kampnaam']        ?? NULL;
            $eventkamp_kampkort              = $array_eventinfo['eventkamp_kampkort']        ?? NULL;
            $eventkamp_kampkort_cap          = $array_eventinfo['eventkamp_kampkort_cap']    ?? NULL;
            $eventkamp_event_start           = $array_eventinfo['eventkamp_event_start']     ?? NULL;
            $eventkamp_kampjaar              = $array_eventinfo['eventkamp_kampjaar']        ?? NULL;
            $eventkamp_kamptype_id           = $array_eventinfo['eventkamp_kamptype_id']     ?? NULL;
            $eventkamp_kamptype_naam         = $array_eventinfo['eventkamp_kamptype_naam']   ?? NULL;
            $eventkamp_event_weeknr          = $array_eventinfo['eventkamp_event_weeknr']    ?? NULL;
        }
    }

    // C. CONTACT INFO: Dit doen we ALTIJD (buiten de if), zodat we de naam van de betaler hebben
    // Hier gebruiken we de veilige $contact_id van de betaler (voorkomt jouw crash!)
    $array_contditjaar      = base_cid2cont($contact_id);
    
    // Naam bepalen: pak die van de betaler als fallback
    $first_name             = $array_contditjaar['first_name']  ?? 'Onbekend'; 
    $displayname            = $array_contditjaar['displayname'] ?? 'Onbekend';

    // D. KENMERK SAMENSTELLEN
    // Let op: als er geen event is, zijn kampkort/kampjaar leeg. Dat is prima, dan wordt het gewoon korter.
    $contribution_kenmerk = trim("Kampgeld $eventkamp_kampkort_cap $eventkamp_kampjaar $first_name");

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 2.0 - WAARDEN BEDRAGEN BIJ ST.GAVE",             "[GAVE]");
    wachthond($extdebug, 1, "#########################################################################");

    // 6. Stichting Gave Regel
    if ($ditevent_part_kampgeld_regeling == 'ja_stgave') {
        $bedrag     = 0;
        $betaald    = 0;
        $balans     = 0;
    }

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 3.0 - WAARDEN BEDRAGEN BIJ DECLARATIES",  "[DECLARATIES]");
    wachthond($extdebug, 1, "#########################################################################");

    // 7. Declaratie Logica (Type 22)
    if ($result_get_contribution['financial_type_id'] == 22) {
        if ($balans < 0 && $status_id != 7) {
            $status_id = 9; // Pending Refund
            wachthond($extdebug, 2, "PECUNIA DECLARATIE", "Saldo negatief ($balans), status naar Pending Refund (9)");
        }
        if ($balans == 0 && $status_id != 7) {
            $status_id = 7; // Refunded
            wachthond($extdebug, 2, "PECUNIA DECLARATIE", "Saldo nul, status naar Refunded (7)");
        }
    }   

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 4.0 - BEPAAL DEFINITIEVE WAARDEN",           "[KAMPGELD]");
    wachthond($extdebug, 1, "#########################################################################");

    // 8. CHECK OF ER UPDATES NODIG ZIJN (Dirty Check)
    $target_values = [
        'total_amount'                  => $bedrag,
        'net_amount'                    => $bedrag,
        'balance_amount'                => $balans,
        'contribution_status_id'        => $status_id,
        'CONT_KAMPGELD.kampnaam'        => $eventkamp_kampnaam,
        'CONT_KAMPGELD.kampkort'        => $eventkamp_kampkort,
        'CONT_KAMPGELD.eventjaar'       => $eventkamp_event_start,
        'CONT_KAMPGELD.kampjaar'        => $eventkamp_kampjaar,
        'CONT_KAMPGELD.kamp_eid'        => $eventkamp_event_id,
        'CONT_KAMPGELD.kamptype_naam'   => $eventkamp_kamptype_naam,
        'CONT_KAMPGELD.kamptype_id'     => $eventkamp_kamptype_id,
        'CONT_KAMPGELD.kampweek_nr'     => $eventkamp_event_weeknr,
        'CONT_KAMPGELD.kenmerk'         => $contribution_kenmerk,
        'CONT_KAMPGELD.regeling'        => $ditevent_part_kampgeld_regeling,
        'CONT_KAMPGELD.bedrag'          => $bedrag,
        'CONT_KAMPGELD.betaald'         => $betaald,
        'CONT_KAMPGELD.saldo'           => $balans,
    ];

    if (!empty($ditevent_register_date)) {
        $target_values['receive_date'] = $ditevent_register_date;
    }

    $clean_values_pecunia = [];
    $has_changes_pecunia  = false;

    foreach ($target_values as $key => $new_val) {
        $old_val = $result_get_contribution[$key] ?? '';

        if (empty($new_val) && empty($old_val)) continue;

        // Vergelijkingslogica voor bedragen, datums en strings
        if (in_array($key, ['total_amount', 'net_amount', 'balance_amount', 'CONT_KAMPGELD.bedrag', 'CONT_KAMPGELD.betaald', 'CONT_KAMPGELD.saldo'])) {
            if (abs((float)$new_val - (float)$old_val) < 0.01) continue; 
        } elseif (in_array($key, ['receive_date', 'CONT_KAMPGELD.eventjaar']) && strlen($old_val) >= 10 && strlen($new_val) >= 10) {
             if (substr($new_val, 0, 10) == substr($old_val, 0, 10)) continue;
        } elseif ($new_val == $old_val) {
            continue;
        }

        wachthond($extdebug, 1, "PECUNIA WIJZIGING GEVONDEN: [$key]", "Oud: '$old_val' -> Nieuw: '$new_val'");
        $clean_values_pecunia[$key] = $new_val;
        $has_changes_pecunia = true;
    }

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 5.0 - VOER UPDATE UIT MET API",                "[UPDATE]");
    wachthond($extdebug, 1, "#########################################################################");

    // 9. UPDATE UITVOEREN (Met Timing)
    if ($has_changes_pecunia) {
        $params_contribution = [
            'checkPermissions' => FALSE,
            'where'     => [['id', '=', $objectId]],
            'values'    => $clean_values_pecunia
        ];

        try {
            watchdog('civicrm_timing', core_microtimer("START EXECUTE Pecunia DB Update"), NULL, WATCHDOG_DEBUG);
            $result_contribution = civicrm_api4('Contribution', 'update', $params_contribution);
            watchdog('civicrm_timing', core_microtimer("EINDE EXECUTE Pecunia DB Update"), NULL, WATCHDOG_DEBUG);
            
            wachthond($extdebug, 1, "result_contribution", "EXECUTED (Wijzigingen opgeslagen)");
        } catch (\Exception $e) {
            wachthond(1, "Fout Pecunia update: " . $e->getMessage());
        }
    } else {
        watchdog('civicrm_timing', core_microtimer("SKIP Pecunia DB Update (Geen wijzigingen)"), NULL, WATCHDOG_DEBUG);
        wachthond($extdebug, 3, "result_contribution", "SKIPPED (Data was reeds up-to-date)");
    }

    $processing_pecunia = FALSE;

    wachthond($extdebug, 1, "#########################################################################");
    wachthond($extdebug, 1, "### PECUNIA POST 5.0 - BEREKEN DATA, STATUS & CAMPAGNE INFO",    "[EINDE]");
    wachthond($extdebug, 1, "#########################################################################");

    watchdog('civicrm_timing', core_microtimer("EINDE TOTALE PECUNIA POST voor contrib_id: $objectId"), NULL, WATCHDOG_DEBUG);
}

/**
 * Implementation of hook_civicrm_config
 */
function pecunia_civicrm_config(&$config) {
  _pecunia_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */

/*
function pecunia_civicrm_xmlMenu(&$files) {
  _pecunia_civix_civicrm_xmlMenu($files);
}
*/

/**
 * Implementation of hook_civicrm_install
 */
function pecunia_civicrm_install() {
  #CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/auto_install.sql');
  return _pecunia_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function pecunia_civicrm_uninstall() {
  #CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/auto_uninstall.sql');
  return;
}

/**
 * Implementation of hook_civicrm_enable
 */
function pecunia_civicrm_enable() {
  return _pecunia_civix_civicrm_enable();
}
