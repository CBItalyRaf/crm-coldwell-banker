<?php
function callNewsAPI($endpoint, $params = [], $method = 'GET', $body = null) {
    $baseUrl = 'https://coldwellbankeritaly.tech/repository/cb-news/public/api/v1';
    $token = '27|O8cC2pZInPq1n3CgX5pYrNnLngsBbuqWgETqICLT2d5c5131';
    
    $url = $baseUrl . $endpoint;
    
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ];
    
    if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!in_array($httpCode, [200, 201, 204])) {
        error_log("News API Error: HTTP $httpCode - $response");
        return null;
    }
    
    $data = json_decode($response, true);
    return $data;
}

/**
 * Costruisci URL completo per immagine
 * Gestisce sia URL assoluti che path relativi
 */
function getFullImageUrl($imageUrl) {
    if(empty($imageUrl)) {
        return null;
    }
    
    // Se già URL completo, usa così com'è
    if(strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0) {
        return $imageUrl;
    }
    
    // Se path relativo, aggiungi dominio
    if(strpos($imageUrl, '/') === 0) {
        return 'https://coldwellbankeritaly.tech' . $imageUrl;
    }
    
    // Altrimenti ritorna così com'è
    return $imageUrl;
}

function getNewsArticles($limit = 10, $search = null, $category = null, $visibility = null, $status = null, $page = null) {
    $params = ['limit' => $limit];
    
    if ($search) {
        $params['search'] = $search;
    }
    
    if ($category) {
        $params['category'] = $category;
    }
    
    if ($visibility) {
        $params['visibility'] = $visibility;
    }
    
    if ($status) {
        $params['status'] = $status;
    }
    
    if ($page) {
        $params['page'] = $page;
    }
    
    return callNewsAPI('/articles', $params);
}

function getNewsArticle($id) {
    return callNewsAPI('/articles/' . $id);
}

function getNewsCategories() {
    static $cache = null;
    
    if ($cache === null) {
        $cache = callNewsAPI('/categories');
    }
    
    return $cache;
}

function updateNewsArticle($id, $data) {
    return callNewsAPI('/articles/' . $id, [], 'PUT', $data);
}

function deleteNewsArticle($id) {
    return callNewsAPI('/articles/' . $id, [], 'DELETE');
}
