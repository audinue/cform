# cform

Convenient HTML form.

## Features

- Submission CRUD
- File uploads
- Simple auth

## PHP API

```php
// Reads all submission in given group
foreach (cform_read_all('comments') as $comment) {
  echo $comment['author'];
}

// Reads a submission in given group by its id
$post = cform_read('posts', $_GET['id']);

// Returns currently logged in user
$user = cform_user();
echo $user['username'];
```

`<input type="file">` is stored as
```php
[
  '_type' => 'upload',
  'name' => 'file_name',
  'size' => 1000,
  'file' => 'path/to/file'
]
```
or `null` if no file(s) is uploaded.

## HTTP API

`cform.php?dump` dumps all submissions and file uploads name to JSON.

## Form API

Arguments can be given through URL query (e.g. `cform.php?_mode=create`) or POST body (e.g. `<input type="hidden" name="_mode" value="create">`).

`_success` is mandatory for all modes.

If a request success redirect to `_success` with query `_ok` otherwise redirect to `_fail` with query `_error=message`.
If no `_fail` given then use `_success` instead.

Do not forget to add `<input type="hidden">` for radios and checkboxes.
```html
<input type="hidden" name="visible">
<input type="checkbox" name="visible">
```

### Create

Creates a new submission.

Parameters: `_mode=create, _group=name, _success=uri, [_fail=uri]`

```html
<!-- create_post.php -->
<form action="cform.php" method="post">
  <input type="hidden" name="_mode" value="create">
  <input type="hidden" name="_group" value="posts">
  <input type="hidden" name="_success" value="posts.php">
  <input name="title" type="text">
  <input type="submit" value="Create Post">
</form>
```

### Update

Merges previously saved submission with current submission. Replaces previously uploaded files with new files if any.

Parameters: `_mode=update, _group=name, _id=id, _success=uri, [_fail=uri]`

```html
<!-- update_post.php -->
<?php
require 'cform.php';
$post = cform_read('posts', $_GET['id']);
?>
<form action="cform.php" method="post">
  <input type="hidden" name="_mode" value="update">
  <input type="hidden" name="_group" value="posts">
  <input type="hidden" name="_id" value="<?= $post['_id'] ?>">
  <input type="hidden" name="_success" value="posts.php">
  <input name="title" type="text" value="<?= $post['title'] ?>">
  <input type="submit" value="Update Post">
</form>
```

### Delete

Deletes a submission.

Parameters: `_mode=update, _group=name, _id=id, _success=uri, [_fail=uri]`

```html
<!-- delete_post.php -->
<form action="cform.php" method="post">
  <input type="hidden" name="_mode" value="delete">
  <input type="hidden" name="_group" value="posts">
  <input type="hidden" name="_id" value="<?= $_GET['id'] ?>">
  <input type="hidden" name="_success" value="posts.php">
  Are you sure do you want to delete the post?
  <input type="submit" value="Yes">
  <a href="posts.php">No</a>
</form>
```

### Register

Register and logs in a new user. Stored in `_users` group.

Parameters:
```
_mode=register
_success=uri
[_fail=uri]
username=username
password=password
repeat_password=repeat_password
```

```html
<!-- register.php -->
<?php
if (isset($_GET['_error'])) {
  echo $_GET['_error'];
}
?>
<form action="cform.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="_mode" value="register">
  <input type="hidden" name="_success" value="dashboard.php">
  <input type="hidden" name="_fail" value="register.php">
  Username: <input type="text" name="username">
  Password: <input type="password" name="password">
  Repeat Password: <input type="password" name="repeat_password">
  <!-- Additional fields -->
  First Name: <input type="text" name="first_name">
  Last Name: <input type="text" name="last_name">
  Photo: <input type="file" name="photo">
  <input type="submit" value="Register">
</form>
```

### Login

Logs in a user.

Parameters:
```
_mode=register
_success=uri
[_fail=uri]
username=username
password=password
```

```html
<!-- login.php -->
<?php
if (isset($_GET['_error'])) {
  echo $_GET['_error'];
}
?>
<form action="cform.php" method="post">
  <input type="hidden" name="_mode" value="login">
  <input type="hidden" name="_success" value="dashboard.php">
  <input type="hidden" name="_fail" value="login.php">
  Username: <input type="text" name="username">
  Password: <input type="password" name="password">
  <input type="submit" value="Login">
</form>
```

### Change Password

Changes user's password.

Parameters:
```
_mode=change_password
_success=uri
[_fail=uri]
old_password=old_password
new_password=new_password
repeat_password=repeat_password
```

```html
<!-- change_password.php -->
<?php
if (isset($_GET['_error'])) {
  echo $_GET['_error'];
}
?>
<form action="cform.php" method="post">
  <input type="hidden" name="_mode" value="change_password">
  <input type="hidden" name="_success" value="dashboard.php">
  <input type="hidden" name="_fail" value="change_password.php">
  Old Password: <input type="password" name="old_password">
  New Password: <input type="password" name="new_password">
  Repeat Password: <input type="password" name="repeat_password">
  <input type="submit" value="Change Password">
</form>
```

### Logout

Logs out current user.

Parameters: `_mode=logout, _success=uri, [_fail=uri]`

```html
<!-- dashboard.php -->
<form action="cform.php?_mode=logout&amp;_success=login.php" method="post">
  <input type="submit" value="Logout">
</form>
```
