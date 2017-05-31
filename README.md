### 介绍 ###

**rxactiverecord**对现有\yii\db\ActiveRecord的增强，提供了Nested操作，以及Polymorphic Relation。

[![Latest Stable Version](https://poser.pugx.org/rainyx/rxkit/v/stable)](https://packagist.org/packages/rainyx/rxkit)
[![License](https://poser.pugx.org/rainyx/rxkit/license)](https://packagist.org/packages/rainyx/rxkit)
[![Total Downloads](https://poser.pugx.org/rainyx/rxkit/downloads)](https://packagist.org/packages/rainyx/rxkit)

### 安装 ###
扩展使用 composer 进行安装。什么是[composer](http://getcomposer.org/download/)？

如需安装，可以在Yii2项目中执行以下命令
```
$ php composer.phar require rainyx/rxactiverecord "*"
```

或者在 `componser.json` 文件中添加依赖

```
"rainyx/rxactiverecord": "*"
```

### 使用 ###
----------

#### 1. Nested ActiveRecord ####
**rxactiverecord** 扩展了 `\yii\db\ActiveRecord` 类，使其可以支持Nested创建、修改其关联对象。

定义Relation
```php
class Article extends \rainyx\rxkit\db\ActiveRecord 
{

    public function getKeywords() 
    {
        return $this->hasMany(Keyword::className(), ['article_id'=>'id']);
    }
    
    public function getContent()
    {
        return $this->hasOne(Content::className, ['article_id'=>'id']);
    }
    
}
```

使用 `setAttributes` 赋值
```php
$article = new Article();
$article->attributes = [
    'keywords'=>[
        ['name'=>'Foo'],
        ['name'=>'Bar'],
    ],
    'content'=>[
        'summary'=>'Blablabla',
        'body'=>'.........',
    ],
];

// keywords 和 content 将会被保存
$article->save();

```

使用 `build` 创建关联对象

```php
$article = new Article();
// Has one
$article->buildContent(['summary'=>'Blablabla']);
// Has many
$article->keywords->build(['name'=>'Foo']);
$article->keywords->build(['name'=>'Bar']);

// keywords 和 content 将会被保存
$article->save();
```

修改已经存在的关联对象

```php
$article = Article::findOne(1);
// $article->content;  // {id: 100, article_id: 1, summary: 'Blablabla'}

$article->attributes = [
    'content'=>[
        'id'=>100,
        'summary'=>'yet another summary...',
    ],
];

// content 将会被更新
$article->save();
```

#### 2. Polymorphic Relation ####

**rxactiverecord** 提供了一种类似其他ORM框架的 Polymorphic Relation，例如Laravel、Ruby On Rails 等。

使用方法

```php
class Comment extends \rainyx\rxkit\db\ActiveRecord 
{
    public function getCommentable()
    {
        return $this->belongsToPolymorphic('model_class', 'model_id');
    }
} 

class Article extends \rainyx\rxkit\db\ActiveRecord 
{
    public function getComments()
    {
        return $this->hasManyPolymorphic(Comment::className(), 'commentable');
    }
}

class Attachment extends \rainyx\rxkit\db\ActiveRecord 
{
    public function getComments()
    {
        return $this->hasManyPolymorphic(Comment::className(), 'commentable');
    }
}

```

客户端代码

```php
$article = Article::findOne(1);
$article->comments->build(['content'=>'Comment 1']); 
$article->save(); // Comment {id: 1}

$attachment = Attachment::findOne(100);
$attachment->comments->build(['content'=>'Comment 2']); 
$attachment->save(); // Comment {id: 2}

$article = Comment::findOne(1)->commentable;
$attachment = Comment::findOne(2)->commentable;

```

### LICENSE ###
----------

MIT License

Copyright (c) 2017 

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
