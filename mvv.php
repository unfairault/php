if ( array_key_exists('file', $_FILES ) && array_key_exists('name', $_FILES['file'] ))
{
    
    //Поместим файл ответа в постоянную папку 
    $today = date("Y-m-d_h-i-s");
    //Сохраняем ответ в /mnt/data/mvd/$evt_id
    $file_name = "/mnt/data_user/word/mvd/{$_GET['evt_id']}/{$_GET['evt_id']}_{$today}_response.xlsx";
    copy( $_FILES['file']['tmp_name'][0], $file_name);
    //Запускаем обработку файла ответа 
    
    //$cmd = "docker exec -i cik_correct_tables ./report/import_from_excel.py {$file_name}"; //в ЦИК
    $cmd = "docker exec -i cik_tables /mnt/excel_export/import_from_excel_old.py {$file_name}"; //на стенде
    
    $output = [];
    $result_code = null;
    exec ( $cmd , $output, $result_code );
       
    //Пишем в базу результат в виде кода ошибки
    $message_for_warning_window = ["size" =>sizeof($output), 0=> 0, 6 => 0, 404 => 0]; //массив с резутатами парсинга ответа. Используем в warning_window
    
    //Header("Content-Type:text/html;charset=utf8");
    foreach ( $output as $line ){
        $passport_plus_result = json_decode($line); //То, что вернулась из : номер паспорта и результат проверки (error_code)
        //0 - соответствует, 6 - не соответствует  , 404 - файл excel заполнен неправильно
        $passport_number = pg_escape_string($passport_plus_result[0]);
        $error_code = intval($passport_plus_result[1]);

        $error_comment = pg_escape_string( strval($passport_plus_result[2]) );
        $error_comment = $error_comment=="None" ? "" : $error_comment;
        $error_comment_2 = pg_escape_string( strval($passport_plus_result[3]) );
        $error_comment_2 = $error_comment_2=="None" ? "" : $error_comment_2;

        $message_for_warning_window[$error_code]++;
        if ($error_code == 0) 
	    $error_code = '';
       
        //Обновляем строки только в своем мероприятиии, т.к. номер паспорта может повторяться много раз (подделки делают)
        db_query_rpd("UPDATE cik_ocr_result 
    		  SET error_comment = '{$error_comment}', error_comment_2 = '{$error_comment_2}'
    		  where ocr_doc_num_corrected = '{$passport_number}' and scn_id in (select scn_id from cik_scans where evt_id = {$evt_id})");
        
        //Код не меняем. Пусть оператор сам ставит. Пробрасываем только комментарий 
        //SET error_codes = '{{$error_code}}', error_comment = '{$error_comment}'
    }
}
