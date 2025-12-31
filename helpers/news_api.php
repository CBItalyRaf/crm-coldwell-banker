<?php
function callNewsAPI($endpoint, $params = []) {
    $baseUrl = 'https://coldwellbankeritaly.tech/repository/cb-news/api/v1';
    $token = '27|O8cC2pZInPq1n3CgX5pYrNnLngsBbuqWgETqICLT2d5c5131';
    
    $url = $baseUrl . $endpoint;
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("News API Error: HTTP $httpCode - $response");
        return null;
    }
    
    $data = json_decode($response, true);
    return $data;
}

function getNewsArticles($limit = 10, $search = null, $category = null, $visibility = null) {
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
