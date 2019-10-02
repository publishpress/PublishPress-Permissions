<?php
$opt_val = is_multisite() ? get_site_meta('pp_support_key') : get_option('pp_support_key');
$renewal_token = (!is_array($opt_val) || count($opt_val) < 2) ? '' : $opt_val[1];

$url = site_url('');
$arr_url = parse_url($url);
$site = urlencode(str_replace($arr_url['scheme'] . '://', '', $url));

wp_redirect('https://publishpress.com/presspermit/?pkg=press-permit-pro&site=' . $site . '&presspermit_account=' . $renewal_token);
exit;