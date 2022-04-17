<?php

namespace api;

function get_me() {
    $wcaoauth = \wcaoauth::me();
    if (!$wcaoauth) {
        http_response_code(401);
        $json = ['error' => 'Not authorized'];
    } else {
        $json = [
            'wca_id' => $wcaoauth->wca_id,
            'name' => $wcaoauth->name,
            'is_admin' => \config::get('Admin', 'wcaid') == $wcaoauth->wca_id
        ];
    }
    return $json;
}
