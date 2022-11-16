# EloquentNestedSet

Tự động cập nhật lại cây khi tạo mới, cập nhật và xóa một node.

Cách sử dụng:

- Đầu tiên, một Root node với `parent_id=0, left=1, right=2, depth=0` cần phải được khởi tạo ở table của bạn
- Thêm `use NestedSetModel;` vào trong Model tương ứng

```php

use MediciVN\EloquentNestedSet\NestedSetModel;

class Category extends Model
{
    use NestedSetModel;

    /**
     * ID của Root node
     * 
     * Mặc định: 1 
     */
    const ROOT_ID = 99999; 

    /**
     * Tên trường lưu vị trí bên trái của một node
     * 
     * Mặc định: 'lft'
     * 
     * Chú ý: kiểu dữ liệu trong Database cần cho phép lưu cả giá trị âm
     */
    const LEFT = 'lft';

    /**
     * Tên trường lưu vị trí bên phải của một node
     * 
     * Mặc định: 'rgt'
     * 
     * Chú ý: kiểu dữ liệu trong Database cần cho phép lưu cả giá trị âm
     */
    const RIGHT = 'rgt';

    /**
     * Tên trường lưu ID của node cha
     * 
     * Mặc định: 'parent_id'
     */
    const PARENT_ID = 'parent_id';

    /**
     * Tên trường lưu giá trị độ sâu - cấp độ của một node
     * 
     * Mặc định: 'depth'
     * 
     * Giá trị depth của một node không ảnh hưởng đến việc tính toán left và rigth.
     * Bạn có thể khởi tạo root node với depth=0, hoặc bất cứ giá trị nào bạn muốn.
     */
    const DEPTH = 'depth';

    /**
     * Bạn có thể triển khai việc cập nhật lại vị trí các nodes với queue nếu lo ngại vấn đề về performance
     * Queue connection phải được khai báo trong `config/queue.php`.
     * 
     * Mặc định: null
     */
    const QUEUE_CONNECTION = 'sqs';

    /**
     * Mặc định: null
     */
    const QUEUE = 'your_queue';

    /**
     * Mặc định: true
     */
    const QUEUE_AFTER_COMMIT = true;

```

## Tính năng

- `getTree`: lấy tất cả nodes và trả về dưới dạng `nested array`

  ```php
      Category::getTree();
  ```

- `getFlatTree`: lấy tất cả nodes và trả về dưới dạng `flatten array`, các nodes con sẽ được sắp xếp ngay sau nút cha

  ```php
      Category::getFlatTree();
  ```

- `getLeafNodes`: lấy tất cả các nodes lá - các node không có con cháu

  ```php
      Category::getLeafNodes();
  ```

- `getAncestors`: lấy tất cả các nodes cha - ông (`ancestor`) của node hiện tại

  ```php
      $node = Category::find(123);
      $node->getAncestors();
  ```

- `getAncestorsTree`: lấy tất cả các nodes cha - ông (`ancestor`) của node hiện tại và trả về dưới dạng `nested array`

  ```php
      $node = Category::find(123);
      $node->getAncestorsTree();
  ```

- `getDescendants`: lấy tất cả các nodes con cháu (`descendant`) của node hiện tại

  ```php
      $node = Category::find(123);
      $node->getDescendants();
  ```

- `getDescendantsTree`: lấy tất cả các nodes con cháu (`descendant`) của node hiện tại và trả về dưới dạng `nested array`

  ```php
      $node = Category::find(123);
      $node->getDescendantsTree();
  ```

- `parent`: lấy node cha của node hiện tại

  ```php
      $node = Category::find(123);
      $node->parent();
  ```

- `children`: lấy tất cả nodes con của node hiện tại

  ```php
      $node = Category::find(123);
      $node->children();
  ```

- `buildNestedTree`: build a nested tree base on `parent_id`

  ```php
      $categories = Category::withoutGlobalScope('ignore_root')->get();
      $tree = Category::buildNestedTree($categories);
  ```

- `fixTree`: tính toán lại toàn bộ tree dựa trên parent_id

  ```php
      Category::fixTree();
  ```

## Query scopes

[Laravel Eloquent Query Scopes](https://laravel.com/docs/9.x/eloquent#query-scopes)

Root node sẽ dự động bị bỏ qua ở tất cả truy vấn bởi `ignore_root` global scope.

Để làm việc với root node, sử dụng `withoutGlobalScope('ignore_root')`.

Các query scope khác:

- `ancestors`
- `descendants`
- `flattenTree`
- `leafNodes`

## Chú ý

- Nếu bạn sử dụng `queue` bạn sẽ cần phải sử dụng thêm `SoftDelete`,
  bởi vì `queue` sẽ thất bại trong trường hợp 1 node bị xóa hoàn toàn,

- Nếu bạn muốn sử dụng SQS-FIFO, hay tham khảo [this package](https://github.com/shiftonelabs/laravel-sqs-fifo-queue#configuration).
