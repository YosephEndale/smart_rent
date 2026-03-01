<?php
// Ensure messages are displayed only if they exist and are non-empty arrays
if (isset($success_msg) && is_array($success_msg) && !empty($success_msg)) {
    foreach ($success_msg as $msg) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                swal({
                    title: 'Success!',
                    text: '" . addslashes(htmlspecialchars($msg)) . "',
                    icon: 'success',
                    button: 'OK'
                });
            });
        </script>";
    }
}

if (isset($warning_msg) && is_array($warning_msg) && !empty($warning_msg)) {
    foreach ($warning_msg as $msg) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                swal({
                    title: 'Warning!',
                    text: '" . addslashes(htmlspecialchars($msg)) . "',
                    icon: 'warning',
                    button: 'OK'
                });
            });
        </script>";
    }
}

if (isset($info_msg) && is_array($info_msg) && !empty($info_msg)) {
    foreach ($info_msg as $msg) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                swal({
                    title: 'Info!',
                    text: '" . addslashes(htmlspecialchars($msg)) . "',
                    icon: 'info',
                    button: 'OK'
                });
            });
        </script>";
    }
}

if (isset($error_msg) && is_array($error_msg) && !empty($error_msg)) {
    foreach ($error_msg as $msg) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                swal({
                    title: 'Error!',
                    text: '" . addslashes(htmlspecialchars($msg)) . "',
                    icon: 'error',
                    button: 'OK'
                });
            });
        </script>";
    }
}
?>