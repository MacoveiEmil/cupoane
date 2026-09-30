<?php

declare(strict_types=1);

namespace Cupons\Core;

/**
 * Numele interne (taxonomii, CPT-uri, meta). Singura sursă de adevăr.
 */
final class Keys
{
    public const PREFIX = 'cupons';

    // Taxonomii
    public const STORE    = 'cupons_magazin';
    public const CATEGORY = 'cupons_categorie';
    public const TAG      = 'cupons_eticheta';

    // CPT-uri
    public const COUPON = 'cupons_cupon';
    public const DEAL   = 'cupons_deal';

    // Meta comune cupon + deal
    public const M_EXPIRES   = '_cupons_expira';     // Y-m-d sau ''
    public const M_START     = '_cupons_start';      // Y-m-d sau ''
    public const M_VERIFIED  = '_cupons_verificat';  // Y-m-d
    public const M_EXCLUSIVE = '_cupons_exclusiv';   // '1' / '0'
    public const M_TOP       = '_cupons_top';        // '1' / '0'
    public const M_EXT_ID    = '_cupons_ext_id';     // „retea:id”
    public const M_RECHECK   = '_cupons_recheck';    // '1' = trecut în ciornă pentru reverificare
    public const M_SEED      = '_cupons_seed';       // date demo

    // Meta interne, normalizate (mereu prezente, pentru interogări simple)
    public const M_FROM  = '_cupons_from';   // start sau 1970-01-01
    public const M_UNTIL = '_cupons_until';  // expirare sau 9999-12-31

    // Meta cupon
    public const M_TYPE       = '_cupons_tip';           // cod | oferta
    public const M_CODE       = '_cupons_cod';
    public const M_VALUE      = '_cupons_valoare';       // număr
    public const M_VALUE_TYPE = '_cupons_tip_reducere';  // procent | lei | transport
    public const M_TERMS      = '_cupons_conditii';
    public const M_LINK       = '_cupons_link';          // override link afiliere

    // Meta deal
    public const M_PRODUCT_URL = '_cupons_url_produs';
    public const M_DEEPLINK    = '_cupons_deeplink';
    public const M_IMAGE       = '_cupons_imagine';      // ID atașament
    public const M_PRICE_OLD   = '_cupons_pret_vechi';
    public const M_PRICE_NEW   = '_cupons_pret_nou';
    public const M_PERCENT     = '_cupons_procent';

    // Term meta magazin
    public const T_LOGO       = '_cupons_logo';        // ID atașament
    public const T_URL        = '_cupons_url';
    public const T_AFF        = '_cupons_afiliere';
    public const T_NETWORK    = '_cupons_retea';       // 2performant | profitshare | direct
    public const T_PROGRAM    = '_cupons_program_id';
    public const T_INFO       = '_cupons_info';        // array
    public const T_FAQ        = '_cupons_faq';         // array de [q, a]
    public const T_CATS       = '_cupons_categorii';   // rânduri multiple, câte un ID de categorie
    public const T_HOME_ORDER = '_cupons_ordine_home'; // int, 0 = nu apare pe homepage
    public const T_UPDATED    = '_cupons_actualizat';  // Y-m-d

    public const DATE_MIN = '1970-01-01';
    public const DATE_MAX = '9999-12-31';

    public const NETWORKS = [
        '2performant' => '2Performant',
        'profitshare' => 'Profitshare',
        'direct'      => 'Direct',
    ];

    public const VALUE_TYPES = [
        'procent'   => '%',
        'lei'       => 'lei',
        'transport' => 'Transport gratuit',
    ];

    /** Câmpurile din „Informații utile” (cheie => etichetă). */
    public const INFO_FIELDS = [
        'transport'     => 'Transport',
        'prag_transport'=> 'Transport gratuit de la',
        'retur'         => 'Retur',
        'plata'         => 'Metode de plată',
        'fidelitate'    => 'Program de fidelitate',
        'prima_comanda' => 'Reducere la prima comandă',
        'studenti'      => 'Reducere pentru studenți',
    ];

    public const DEFAULT_TAGS = [
        'transport-gratuit' => 'Transport gratuit',
        'prima-comanda'     => 'Prima comandă',
        'studenti'          => 'Studenți',
        'cumulabil'         => 'Cumulabil',
    ];

    /** Tipurile de obiect din tabela de click-uri. */
    public const CLICK_COUPON = 1;
    public const CLICK_DEAL   = 2;
    public const CLICK_STORE  = 3;

    public static function offer_types(): array
    {
        return [self::COUPON, self::DEAL];
    }
}
