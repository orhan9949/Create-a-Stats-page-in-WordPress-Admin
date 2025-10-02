<?php

class Stats_Page {

    function __construct () {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }

    /**
     * @return void
     */
    function admin_menu () {
        add_options_page( 'Stats','Stats','manage_options','stats', [ $this, 'stats_page' ] );
    }

    /**
     * @return void
     */
    function stats_page () {
        $this->stats_author();
        $this->stats_category();
        $this->stats_category_by_year();
    }

    /**
     * @param array $args
     */
    function table_html (array $args = []) {
        $table_name = $args['table_name'];
        $table_head = '';
        $table_body = '';
        $table_foot = '<th><strong>Total</strong></th>';
        $total_count_in_columns = [];

        foreach ($args['header_names'] as $header_name) {
            $table_head .= '<th><strong>' . $header_name . '</strong></th>';
        }

        foreach ($args['table_body_content'] as $body_content) {

            $tr = '<tr>';
            $count_td = 0;

            foreach ($body_content as $line_content) {
                if($count_td == 0){
                    $tr .= '<td><strong>' . $line_content . '</strong></td>';
                }else{
                    $tr .= '<td>' . $line_content . '</td>';
                    $total_count_in_columns[$count_td] += $line_content;
                }
                $count_td++;
            }

            $tr .= '</tr>';
            $table_body .= $tr;
        }

        foreach ($total_count_in_columns as $total_count) {
            $table_foot .= '<th><strong>' . $total_count . '</strong></th>';
        }

        echo sprintf("<div class='wrap'>
                                <br>
                                <br>
                                <p><strong>%s</strong></p>
                                <table class=\"widefat fixed striped\">
                                    <thead>
                                        <tr>%s</tr>
                                    </thead>
                                    <tbody>%s</tbody>
                                    <tfoot>
                                        <tr>%s</tr>
                                    </tfoot>
                                </table>
                            </div>", $table_name, $table_head, $table_body, $table_foot);
    }

    /**
     * @return array|object|stdClass[]|null
     */
    function request_stats_author () {
        global $wpdb;

        $result = $wpdb->get_results(
            "SELECT 
                    u.ID AS author_id,
                    u.display_name AS author_name,
                    SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END) AS current_month,
                    SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') THEN 1 ELSE 0 END) AS last_month,
                    SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m') THEN 1 ELSE 0 END) AS two_months_ago
                FROM wp_posts p
                JOIN wp_users u ON p.post_author = u.ID
                WHERE p.post_status = 'publish'
                  AND p.post_type = 'post'
                  AND p.post_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                GROUP BY u.ID, u.display_name
                ORDER BY u.display_name ASC;",ARRAY_A
        );

        return $result;
    }

    /**
     * @return array|object|stdClass[]|null
     */
    function request_stats_category () {
        global $wpdb;

        $result = $wpdb->get_results(
            "SELECT 
                        t.term_id AS category_id,
                        t.name AS category_name,
                        SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END) AS current_month,
                        SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') THEN 1 ELSE 0 END) AS last_month,
                        SUM(CASE WHEN DATE_FORMAT(p.post_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m') THEN 1 ELSE 0 END) AS two_months_ago
                    FROM wp_posts p
                    JOIN wp_term_relationships tr ON p.ID = tr.object_id
                    JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN wp_terms t ON tt.term_id = t.term_id
                    WHERE p.post_status = 'publish'
                      AND p.post_type = 'post'
                      AND tt.taxonomy = 'category'
                      AND p.post_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                    GROUP BY t.term_id, t.name
                    ORDER BY t.name ASC;",ARRAY_A
        );

        return $result;
    }

    /**
     * @return array|object|stdClass[]|null
     */
    function request_stats_category_by_year () {
        global $wpdb;

        $result = $wpdb->get_results(
            "SELECT 
                        t.term_id AS category_id,
                        t.name AS category_name,
                        SUM(CASE WHEN YEAR(p.post_date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS current_year,
                        SUM(CASE WHEN YEAR(p.post_date) = YEAR(CURDATE()) - 1 THEN 1 ELSE 0 END) AS last_year,
                        SUM(CASE WHEN YEAR(p.post_date) = YEAR(CURDATE()) - 2 THEN 1 ELSE 0 END) AS two_years_ago
                    FROM wp_posts p
                    JOIN wp_term_relationships tr ON p.ID = tr.object_id
                    JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    JOIN wp_terms t ON tt.term_id = t.term_id
                    WHERE p.post_status = 'publish'
                      AND p.post_type = 'post'
                      AND tt.taxonomy = 'category'
                      AND YEAR(p.post_date) >= (YEAR(CURDATE()) - 2) -- Last three years
                    GROUP BY t.term_id, t.name
                    ORDER BY t.name;",ARRAY_A
        );

        return $result;
    }

    /**
     * @return void
     */
    function stats_author () {

        $result = $this->request_stats_author();

        $table_body_content = [];

        foreach($result as $author){
            $author_array = [];
            $author_array['author_name'] = $author['author_name'];
            $author_array['two_months_ago'] = $author['two_months_ago'];
            $author_array['last_month'] = $author['last_month'];
            $author_array['current_month'] = $author['current_month'];

            $table_body_content[] = $author_array;
        }

        $args = [
            'table_name' => 'Stats by Author',
            'header_names' => [
                '0' => 'Author Name',
                '1' => date("F Y", mktime(0,0,0,date("m")-2,date("d"), date("Y")) ),
                '2' => date("F Y", mktime(0,0,0,date("m")-1,date("d"), date("Y")) ),
                '3' => date("F Y", mktime(0,0,0,date("m"),date("d"), date("Y")) ),
            ],
            'table_body_content' => $table_body_content,
        ];

        $this->table_html($args);

    }

    /**
     * @return void
     */
    function stats_category () {

        $result = $this->request_stats_category();

        $table_body_content = [];

        foreach($result as $author){
            $author_array = [];
            $author_array['category_name'] = $author['category_name'];
            $author_array['two_months_ago'] = $author['two_months_ago'];
            $author_array['last_month'] = $author['last_month'];
            $author_array['current_month'] = $author['current_month'];

            $table_body_content[] = $author_array;
        }

        $args = [
            'table_name' => 'Stats by Category',
            'header_names' => [
                '0' => 'Category',
                '1' => date("F Y", mktime(0,0,0,date("m")-2,date("d"), date("Y")) ),
                '2' => date("F Y", mktime(0,0,0,date("m")-1,date("d"), date("Y")) ),
                '3' => date("F Y", mktime(0,0,0,date("m"),date("d"), date("Y")) ),
            ],
            'table_body_content' => $table_body_content,
        ];

        $this->table_html($args);

    }

    /**
     * @return void
     */
    function stats_category_by_year () {

        $result = $this->request_stats_category_by_year();

        $table_body_content = [];

        foreach($result as $author){
            $author_array = [];
            $author_array['category_name'] = $author['category_name'];
            $author_array['two_years_ago'] = $author['two_years_ago'];
            $author_array['last_year'] = $author['last_year'];
            $author_array['current_year'] = $author['current_year'];

            $table_body_content[] = $author_array;
        }

        $args = [
            'table_name' => 'Stats by Category by Year',
            'header_names' => [
                '0' => 'Category',
                '1' => date("Y", mktime(0,0,0,date("m"),date("d"), date("Y")-2) ),
                '2' => date("Y", mktime(0,0,0,date("m"),date("d"), date("Y")-1) ),
                '3' => date("Y", mktime(0,0,0,date("m"),date("d"), date("Y")) ),
            ],
            'table_body_content' => $table_body_content,
        ];

        $this->table_html($args);

    }

}

new Stats_Page;