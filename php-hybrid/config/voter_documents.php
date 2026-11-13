<?php
return [
 'max_files'=>max(1,min(100,(int)env('PDF_MAX_FILES_PER_UPLOAD',100))),
 'max_file_mb'=>max(1,(int)env('PDF_MAX_FILE_MB',50)),
];
