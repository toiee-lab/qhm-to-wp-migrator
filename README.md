# QHM Migrator (QHM移行 WordPressプラグイン)

このプラグインは、QHMで作成したサイトをWordPressへ移行させることを支援します。詳しい使い方は、以下のページをご覧ください。


## 機能概要

QHM Migrator は以下の３つの機能があります。

1. **準備期間のQHMへの転送機能** : WordPressサイトを準備している間は、一般の訪問者には、QHMのページを表示させる機能です。サイトへのアクセスをチェックして、ログインしていなければ、 index_qhm.php に302転送を行います。
2. **インポート機能** : QHMのページ、ブログを解析し、swfu/d/ 内にある実際に使われている、リンクされている画像やファイルを移行します。また、ページは「固定ページ」、QBlogは「投稿」として、WordPressに登録します。
3. **QHMのURLをWordPressのURLに転送** : QHMのURLは、 `index.php?HogeHoge` となります。これを `/HogeHoge/` として転送します。なお、インポートされたページや投稿は、 `/Hogehoge/` スラグとして登録されているので、正しく転送されます。


## ご利用方法

以下のように作業をしてください。

1. **バックアップする** : いつでも、元に戻せるようにバックアップをしてください(FTPソフトを使うなどしてください)
2. **QHMを最新にする** : このプラグインは、PHP5.6以上でないと動作しません。QHMが古すぎると、QHM5.6でエラーが出てQHM自体が動きません（そうなるとインポートできません）。[QHMを最新にする方法はこちら](https://qhm2wp.toiee.jp/manual/self/qhm-v4-to-v5/)
3. **PHPのバージョンを5.6以上にする** : PHPをこのプラグインが利用できる5.6以上にアップデートしてください
4. **WordPressをダウンロード** : WordPressの公式サイトで、最新のWordPressをダウンロードする
5. **WordPressのindex.phpをindex_wp.phpに変更** : WordPressをアップロードする前に、index.php を index_wp.php に名前を変更してください。これを忘れると、QHMのindex.phpが上書きされて、QHMが表示されなくなります。その場合は、QHMのindex.phpで再度上書きしてください。
6. **WordPressをアップロード** : WordPressをQHMを設置している場所にアップロードします
7. **WordPressを設定** : /wp-admin/ にアクセスして、WordPressの設定を行います。事前にデータベースの作成、ユーザー追加などを行っておきます（お使いのレンタルサーバーのマニュアルを参照）
8. **このプラグインをインストール、有効化** : 上記のダウンロードリンクから、ダウンロードします。次に プラグイン > 新規インストール　でこのプラグインのファイルを指定し、アップロードし、有効化します
9. **index.php を入れ替え、index_qhm.php を用意する** : QHMのindex.php を index_qhm.php に、WordPressの index.php (index_wp.php) を index.php とします
10. **QHMの移行作業** : 設定>QHM移行 に進むと、インポートなどができます。これらを使ってデータをインポートします。またWordPressの見た目などを調整します。この間、一般ユーザーは、QHMが表示されています
11. **QHM移行完了する** : 設定>QHM移行で、「移行完了」を設定します。すると、QHMには転送せず、WordPressを表示するようになります。


[上記の手順の詳しいマニュアルとサポートをご用意しています（募集が終了している場合があります）。](https://qhm2wp.toiee.jp/manual/self/)



## 仕様

### 仕組み

このプラグインは、 index_qhm.php にアクセスして、 `<!-- BODYCONTENTS START -->` から `<!-- BODYCONTENTS END -->` までのHTMLを取り込みます。取り込んだHTMLファイルを解析して、ブログ投稿に必要なタイトルなどを取得しています。
	
もし、QHMのテンプレートファイルを独自に改変して、上記のコメントが表示されない場合は正常に動作しません。

### 注意点

- WordPressをクリーンインストールした直後に利用することを想定しています
- ２回の取り込みなどについては、考慮していません
- 既存の投稿、カテゴリに対する配慮はありません

### できること

- 隠しページ、通常のページを全て読み込みます
- :config や SiteNavigation などのコンテンツ以外のページは読み込みません
- ブログのカテゴリを登録、反映します
- ページ、ブログに使われている画像やファイルは、取り込まれますが、それ以外は取り込みません
- 取り込む対象のファイルは、swfu/d フォルダ以下に限ります


## Change log

- [詳細は、こちら](https://github.com/toiee-lab/wordpress-to-qhm-migrator/commits/master)

### ver 0.7 (Aug 9, 2017)
- 移行を行いやすくするために、便利なリンクを追加しました

### ver 0.6 (Aug 8, 2017)
- アップデート通知を受け取れるように修正しました
- index.php、index_qhm.php が間違っているときに修正するための方法を追加しました

### ver 0.5 (Aug 7, 2017)

- WordPressにログインしているなら、QHMを公開している状態でも、index.php?XXXX を /XXXX/ に転送するように修正
- Readmeに移行のための手順を記載


### ver 0.4 (Jul 24, 2017)

**既存ページを読み込まないオプションの追加、attachを取り込む、実行時間の延長*

- 既存の投稿、ページをslug で検索をかけて、取り込まずにスキップできるようにしました
- これは、実行時間が短いサーバー、処理が遅いサーバー、ページが多すぎる場合に、数回に分けて、ページをインポートするのに役立ちます
- PHPの実行時間を180秒に延長することを試み見ます。これにより、かなり長く実行できるようになるはずです
- 今回のバージョンから、古い添付ファイル（refを使った attachフォルダのもの) も取り込めるように改良しました

### ver 0.3 (Jul 15, 2017)

**PHP5.6以上でないと動かないようにチェックをかける**

- PHP5.6以上でないと、動作できないように修正。
- 不要なコメントなどを削除
- PHPの実行環境情報を記載

### ver 0.2

**PHP5.4に対応**

- メンバ変数、メソッドの修飾子などを削除




