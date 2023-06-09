<?php
if (isset($_GET['code'])) {
    echo 'Received authorization code: ' . $_GET['code'];
} else {
    echo 'Error: No authorization code received.';
}
