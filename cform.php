<?php

function _cform_read($file) {
    $content = @file_get_contents($file);
    if ($content === false) {
        throw new Exception("Unable to read $file");
    }
    $json = @json_decode($content, true);
    if ($json === null) {
        throw new Exception("Unable to decode $file");
    }
    return $json;
}

function cform_read_all($group) {
    $submissions = [];
    foreach (glob(_cform_submission($group, '*')) as $file) {
        $submissions []= _cform_read($file);
    }
    return $submissions;
}

function _cform_submission($group, $id) {
    return ".cform/submissions/$group/$id.json";
}

function cform_read($group, $id) {
    return _cform_read(_cform_submission($group, $id));
}

function _cform_write($group, $submission) {
    if (empty($submission['_id'])) {
        throw new Exception('Missing _id');
    }
    @mkdir(".cform/submissions/$group", 0777, true);
    $file = _cform_submission($group, $submission['_id']);
    $json = @json_encode(array_diff_key($submission, [
        '_group'   => null,
        '_mode'    => null,
        '_success' => null,
        '_fail'    => null,
    ]), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new Exception("Unable to encode $file");
    }
    if (!@file_put_contents($file, $json, LOCK_EX)) {
        throw new Exception("Unable to write $file");
    }
}

function _cform_is_upload($value) {
    return is_array($value) && @$value['_type'] == 'upload';
}

function _cform_is_upload_array($value) {
    return is_array($value) && _cform_is_upload(@$value[0]);
}

function _cform_delete_upload($upload) {
    $file = $upload['file'];
    if (!@unlink($file)) {
        throw new Exception("Unable to delete $file");
    }
}

function _cform_delete_if_upload($value) {
    if (_cform_is_upload_array($value)) {
        foreach ($value as $upload) {
            _cform_delete_upload($upload);
        }
    } elseif (_cform_is_upload($value)) {
        _cform_delete_upload($value);
    }
}

function _cform_upload_params($value) {
    $upload_params = [];
    foreach ($value['tmp_name'] as $index => $tmp_name) {
        $upload_params []= [
            'tmp_name' => $tmp_name,
            'name' => $value['name'][$index],
            'size' => $value['size'][$index],
        ];
    }
    return $upload_params;
}

function _cform_create_upload($group, $upload_param) {
    if (is_uploaded_file($upload_param['tmp_name'])) {
        $id = uniqid();
        $extension = pathinfo($upload_param['name'], PATHINFO_EXTENSION);
        @mkdir(".cform/files/$group", 0777, true);
        $file = ".cform/files/$group/$id.$extension";
        if (!@move_uploaded_file($upload_param['tmp_name'], $file)) {
            throw new Exception("Unable to upload $key");
        }
        return [
            '_type' => 'upload',
            'file' => $file,
            'name' => $upload_param['name'],
            'size' => $upload_param['size']
        ];
    }
}

function _cform_upload($group, $previous, $files) {
    foreach ($files as $key => $value) {
        if (is_array($value['tmp_name'])) {
            $upload_array = [];
            foreach (_cform_upload_params($value) as $upload_param) {
                $upload = _cform_create_upload($group, $upload_param);
                if ($upload) {
                    $upload_array []= $upload;
                }
            }
            if (!empty($upload_array)) {
                _cform_delete_if_upload(@$previous[$key]);
                $previous[$key] = $upload_array;
            } elseif (!isset($previous[$key])) {
                $previous[$key] = [];
            }
        } else {
            $upload = _cform_create_upload($group, $value);
            if ($upload) {
                _cform_delete_if_upload(@$previous[$key]);
                $previous[$key] = $upload;
            } elseif (!isset($previous[$key])) {
                $previous[$key] = null;
            }
        }
    }
    return $previous;
}

function _cform_create($group, $submission, $files) {
    $submission = array_merge(
        $submission,
        _cform_upload($group, [], $files),
        ['_id' => uniqid()]
    );
    _cform_write($group, $submission);
    return $submission;
}

function _cform_update($group, $submission, $files) {
    $submission = array_merge(
        _cform_upload($group, cform_read($group, $submission['_id']), $files),
        $submission
    );
    _cform_write($group, $submission);
    if ($group == '_users') {
        if (!session_start()) {
            throw new Exception('Unable to start session');
        }
        if (@$_SESSION['_cform_user']['_id'] == $submission['_id']) {
            $_SESSION['_cform_user'] = $submission;
        }
    }
}

function _cform_delete($group, $id) {
    $submission = cform_read($group, $id);
    foreach ($submission as $value) {
        _cform_delete_if_upload($value);
    }
    $file = _cform_submission($group, $id);
    if (!@unlink($file)) {
        throw new Exception("Unable to delete $file");
    }
}

function _cform_append_query($uri, $suffix) {
    return $uri . (strpos($uri, '?') === false ? '?'  : '&') . $suffix;
}

function cform_user() {
    if (!session_start()) {
        throw new Exception('Unable to start session');
    }
    return @$_SESSION['_cform_user'];
}

function _cform_register($submission, $files) {
    if (empty($submission['username'])) {
        throw new Exception('Empty username');
    }
    if (empty($submission['password'])) {
        throw new Exception('Empty password');
    }
    if (empty($submission['repeat_password'])) {
        throw new Exception('Empty repeat password');
    }
    if ($submission['password'] != $submission['repeat_password']) {
        throw new Exception("Repeat password doesn't match");
    }
    foreach (cform_read_all('_users') as $user) {
        if ($user['username'] == $submission['username']) {
            throw new Exception('Username already exists');
        }
    }
    unset($submission['repeat_password']);
    $submission['password'] = md5($submission['password']);
    $user = _cform_create('_users', $submission, $files);
    if (!session_start()) {
        throw new Exception('Unable to start session');
    }
    $_SESSION['_cform_user'] = $user;
}

function _cform_login($submission) {
    if (empty($submission['username'])) {
        throw new Exception('Empty username');
    }
    if (empty($submission['password'])) {
        throw new Exception('Empty password');
    }
    $password = md5($submission['password']);
    foreach (cform_read_all('_users') as $user) {
        if ($user['username'] == $submission['username'] && $user['password'] == $password) {
            if (!session_start()) {
                throw new Exception('Unable to start session');
            }
            $_SESSION['_cform_user'] = $user;
            return;
        }
    }
    throw new Exception('Invalid username or password');
}

function _cform_change_password($submission) {
    $user = cform_user();
    if (empty($submission['old_password'])) {
        throw new Exception('Empty old password');
    }
    if (empty($submission['new_password'])) {
        throw new Exception('Empty password');
    }
    if (empty($submission['repeat_password'])) {
        throw new Exception('Empty repeat password');
    }
    if ($submission['new_password'] != $submission['repeat_password']) {
        throw new Exception("Repeat password doesn't match");
    }
    if (md5($submission['old_password']) != $user['password']) {
        throw new Exception('Invalid old password');
    }
    $new_password = md5($submission['new_password']);
    _cform_update('_users', [
        '_id' => $user['_id'],
        'password' => $new_password
    ], []);
    $user['password'] = $new_password;
}

function _cform_logout() {
    if (!session_start()) {
        throw new Exception('Unable to start session');
    }
    if (!session_destroy()) {
        throw new Exception('Unable to destroy session');
    }
}

if ($_SERVER['SCRIPT_FILENAME'] == __FILE__) {
    if (@$_SERVER['REQUEST_METHOD'] == 'GET' && isset($_REQUEST['dump'])) {
        $dump = [
            'submissions' => [],
            'files' => []
        ];
        foreach (array_diff(scandir('.cform/submissions'), ['.', '..']) as $group_name) {
            $group = [];
            foreach (array_diff(scandir(".cform/submissions/$group_name"), ['.', '..']) as $file_name) {
                $group []= _cform_read(".cform/submissions/$group_name/$file_name");
            }
            $dump['submissions'][$group_name] = $group;
        }
        foreach (array_diff(scandir('.cform/files'), ['.', '..']) as $group_name) {
            $group = [];
            foreach (array_diff(scandir(".cform/files/$group_name"), ['.', '..']) as $file_name) {
                $group []= $file_name;
            }
            $dump['files'][$group_name] = $group;
        }
        header('Content-Type: application/json');
        echo json_encode($dump, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } elseif (@$_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            if (empty($_REQUEST['_mode'])) {
                throw new Exception('Missing _mode');
            }
            if (!in_array($_REQUEST['_mode'], ['create', 'update', 'delete', 'register', 'login', 'change_password', 'logout'])) {
                throw new Exception('Invalid _mode');
            }
            if (empty($_REQUEST['_success'])) {
                throw new Exception('Missing _success');
            }
            if (in_array($_REQUEST['_mode'], ['create', 'update', 'delete']) && empty($_REQUEST['_group'])) {
                throw new Exception('Missing _group');
            }
            if (in_array($_REQUEST['_mode'], ['update', 'delete']) && empty($_REQUEST['_id'])) {
                throw new Exception('Missing _id');
            }
            if ($_REQUEST['_mode'] == 'register') {
                if (!isset($_REQUEST['username'])) {
                    throw new Exception('Missing username');
                } elseif (!isset($_REQUEST['password'])) {
                    throw new Exception('Missing password');
                } elseif (!isset($_REQUEST['repeat_password'])) {
                    throw new Exception('Missing repeat_password');
                }
            } elseif ($_REQUEST['_mode'] == 'login') {
                if (!isset($_REQUEST['username'])) {
                    throw new Exception('Missing username');
                } elseif (!isset($_REQUEST['password'])) {
                    throw new Exception('Missing password');
                }
            } elseif ($_REQUEST['_mode'] == 'change_password') {
                if (!isset($_REQUEST['old_password'])) {
                    throw new Exception('Missing old_password');
                } elseif (!isset($_REQUEST['new_password'])) {
                    throw new Exception('Missing new_password');
                } elseif (!isset($_REQUEST['repeat_password'])) {
                    throw new Exception('Missing repeat_password');
                }
            }
            try {
                switch ($_REQUEST['_mode']) {
                    case 'create':
                        _cform_create($_REQUEST['_group'], $_REQUEST, $_FILES);
                        break;
                    case 'update':
                        _cform_update($_REQUEST['_group'], $_REQUEST, $_FILES);
                        break;
                    case 'delete':
                        _cform_delete($_REQUEST['_group'], $_REQUEST['_id']);
                        break;
                    case 'register':
                        _cform_register($_REQUEST, $_FILES);
                        break;
                    case 'login':
                        _cform_login($_REQUEST);
                        break;
                    case 'change_password':
                        _cform_change_password($_REQUEST);
                        break;
                    case 'logout':
                        _cform_logout();
                        break;
                    default:
                        throw new Exception('Not implemented yet');
                }
                header('Location: ' . _cform_append_query($_REQUEST['_success'], '_ok'));
            } catch (Exception $e) {
                $fail = empty($_REQUEST['_fail']) ? $_REQUEST['_success'] : $_REQUEST['_fail'];
                header('Location: ' . _cform_append_query($fail, '_error=' . urlencode($e->getMessage())));
            }
        } catch (Exception $e) {
            header('HTTP/1.0 400 Bad Request');
            header('Content-Type: text/plain');
            echo $e->getMessage();
        }
    }
}
