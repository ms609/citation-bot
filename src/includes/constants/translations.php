<?php

declare(strict_types=1);

// Also need added to: both Page.php case statements, setup.php list of valid wikis, and both places in index.html

const MK_ERR1 = 'Следниот текст може да ви помогне да сфатите каде е грешката на страницата (Барајте само { и } знаци или незатворен коментар)';
const MK_ERR2 = 'Ако тоа не е проблемот, тогаш стартувајте ја единствената страница со &prce=1 додадена на URL-то за да го промените моторот за парсирање';

const RU_ERR1 = 'Следующий текст может помочь вам выяснить, где находится ошибка на странице (ищите одинокие символы { и } или незакрытый комментарий)';
const RU_ERR2 = 'Если проблема не в этом, то запустите отдельную страницу с &prce=1, добавленным к URL, чтобы изменить механизм синтаксического анализа.';

const SR_ERR1 = 'Следећи текст би вам могао помоћи да схватите где је грешка на страници (потражите усамљене знакове { и } или незатворени коментар).';
const SR_ERR2 = 'Ако то није проблем, онда покрените једну страницу са додатком &prce=1 у URL да бисте променили механизам за парсирање';

const ENG_ERR1 = 'The following text might help you figure out where the error on the page is (Look for lone { and } characters, or unclosed comment)';
const ENG_ERR2 = 'If that is not the problem, then run the single page with &prce=1 added to the URL to change the parsing engine';

const VI_ERR1 = 'Đoạn văn bản sau có thể giúp bạn xác định lỗi trên trang (Tìm các ký tự { hoặc } đơn lẻ, hoặc comment chưa đóng)';
const VI_ERR2 = 'Nếu đó không phải là vấn đề, hãy chạy trên một trang riêng lẻ với &prce=1 thêm vào URL để thay đổi trình phân tích cú pháp';

/* links to verify
https://mk.wikipedia.org/wiki/Template:Cite_journal
https://mk.wikipedia.org/wiki/Template:Cite_book
https://mk.wikipedia.org/wiki/Template:Cite_web
https://mk.wikipedia.org/wiki/Template:Cite_magazine
https://mk.wikipedia.org/wiki/Template:Citation
https://mk.wikipedia.org/wiki/Template:Cite_arxiv
https://mk.wikipedia.org/wiki/Template:Cite_news
https://mk.wikipedia.org/wiki/Template:Cite_document
https://mk.wikipedia.org/wiki/Template:Cite_conference

https://ru.wikipedia.org/wiki/Template:Cite_journal
https://ru.wikipedia.org/wiki/Template:Cite_book
https://ru.wikipedia.org/wiki/Template:Cite_web
https://ru.wikipedia.org/wiki/Template:Cite_magazine
https://ru.wikipedia.org/wiki/Template:Citation
https://ru.wikipedia.org/wiki/Template:Cite_arxiv
https://ru.wikipedia.org/wiki/Template:Cite_news
https://ru.wikipedia.org/wiki/Template:Cite_document
https://ru.wikipedia.org/wiki/Template:Cite_conference

https://sr.wikipedia.org/wiki/Template:Cite_journal
https://sr.wikipedia.org/wiki/Template:Cite_book
https://sr.wikipedia.org/wiki/Template:Cite_web
https://sr.wikipedia.org/wiki/Template:Cite_magazine
https://sr.wikipedia.org/wiki/Template:Citation
https://sr.wikipedia.org/wiki/Template:Cite_arxiv
https://sr.wikipedia.org/wiki/Template:Cite_news
https://sr.wikipedia.org/wiki/Template:Cite_document
https://sr.wikipedia.org/wiki/Template:Cite_conference

https://vi.wikipedia.org/wiki/Template:Cite_journal
https://vi.wikipedia.org/wiki/Template:Cite_book
https://vi.wikipedia.org/wiki/Template:Cite_web
https://vi.wikipedia.org/wiki/Template:Cite_magazine
https://vi.wikipedia.org/wiki/Template:Citation
https://vi.wikipedia.org/wiki/Template:Cite_arxiv
https://vi.wikipedia.org/wiki/Template:Cite_news
https://vi.wikipedia.org/wiki/Template:Cite_document
https://vi.wikipedia.org/wiki/Template:Cite_conference
*/

// WARNING: mb_strtolower versions - code also assumes that only the first character could be uppercase
const MK_TEMPLATES_MAP = [
    'наведено списание' => 'cite journal',
    'наведена книга' => 'cite book',
    'наведена мрежна страница' => 'cite web',
    'наведен нестручен часопис' => 'cite magazine',
    'наведување' => 'citation',
    'наведен arxiv' => 'cite arxiv',
    'наведени вести' => 'cite news',
    'Hаведено списание' => 'cite document', // THIS IS CITE JOURNAL in MK, since cite document does not exist this. Use capital H so that it is not a duplicate key
    'наведен научен собир' => 'cite conference',
];
const VI_TEMPLATES_MAP = [
    'chú thích tập san học thuật' => 'cite journal',
    'chú thích sách' => 'cite book',
    'chú thích web' => 'cite web',
    'chú thích tạp chí' => 'cite magazine',
    'chú thích' => 'citation',
    'chú thích arxiv' => 'cite arxiv',
    'chú thích báo' => 'cite news',
    'chú thích tài liệu' => 'cite document',
    'chú thích hội thảo' => 'cite conference',
];
const ALL_TEMPLATES_MAP = [MK_TEMPLATES_MAP, VI_TEMPLATES_MAP];

const MK_TRANS = [
    'Altered' => 'Променет',
    'Alter:' => 'Промени:',
    'URLs might have been anonymized. ' => 'УРЛ-адресите можеби биле анонимизирани. ',
    'Added' => 'Додадено',
    'Add:' => 'Додај:',
    'Removed or converted URL. ' => 'Отстранет или конвертиран URL. ',
    'Removed URL that duplicated identifier. ' => 'Отстранет URL-то дупликат идентификатор. ',
    'Removed access-date with no URL. ' => 'Отстранет датумот на пристап без URL. ',
    'Changed bare reference to CS1/2. ' => 'Променета гола референца на CS1/2. ',
    'Removed parameters. ' => 'Отстранети параметри. ',
    'Some additions/deletions were parameter name changes. ' => 'Некои дополнувања/бришења беа промени во името на параметрите. ',
    'Upgrade ISBN10 to 13. ' => 'Надградете го ISBN10 на 13. ',
    'Removed Template redirect. ' => 'Отстранет пренасочување на шаблонот. ',
    'Misc citation tidying. ' => 'Средување на различни цитати. ',
    'Use this bot]].' => 'Користете го овој софтвер]].',
    '|Report bugs]]' => '|Пријави грешки]]',
    'Formatted ' => 'Форматиран ',
    'Suggested by' => 'Предложено од',
    'Linked from' => 'Поврзано од',
    '[[Category:' => '[[Категорија:',
];

const RU_TRANS = [
    'Altered' => 'Изменен',
    'Alter:' => 'Изменены:',
    'URLs might have been anonymized. ' => 'URL-адреса могли быть анонимизированы. ',
    'Added' => 'Добавлен',
    'Add:' => 'Добавлены:',
    'Removed or converted URL. ' => 'URL-адрес удален или преобразован. ',
    'Removed URL that duplicated identifier. ' => 'Удален URL-адрес, который дублировал идентификатор. ',
    'Removed access-date with no URL. ' => 'Удален access-date без URL-адреса. ',
    'Changed bare reference to CS1/2. ' => 'Голые ссылка изменены на CS1/2. ',
    'Removed parameters. ' => 'Удалены параметры. ',
    'Some additions/deletions were parameter name changes. ' => 'Некоторые добавления/удаления были изменениями имен параметров. ',
    'Upgrade ISBN10 to 13. ' => 'Обновление ISBN10 до 13. ',
    'Removed Template redirect. ' => 'Удалены перенаправления на шаблоны. ',
    'Misc citation tidying. ' => 'Различные исправления источников. ',
    'Use this bot]].' => 'Как использовать бота]].',
    '|Report bugs]]' => '|Сообщить об ошибке]]',
    'Formatted ' => 'Отформатировано ',
    'Suggested by' => 'Предложено',
    'Linked from' => 'Ссылки с',
    '[[Category:' => '[[Категория:',
];

const SR_TRANS = [
    'Altered' => 'Промењен',
    'Alter:' => 'Промени:',
    'URLs might have been anonymized. ' => 'УРЛ адресе су можда анонимизиране. ',
    'Added' => 'Додано',
    'Add:' => 'Додај:',
    'Removed or converted URL. ' => 'Уклоњен или конвертован URL. ',
    'Removed URL that duplicated identifier. ' => 'Уклоњен URL који је дуплирао идентификатор. ',
    'Removed access-date with no URL. ' => 'Уклоњен датум приступа без URL. ',
    'Changed bare reference to CS1/2. ' => 'Промењене гола референце на CS1/2. ',
    'Removed parameters. ' => 'Уклоњени параметри. ',
    'Some additions/deletions were parameter name changes. ' => 'Неке допуне/брисања без промене имена параметра. ',
    'Upgrade ISBN10 to 13. ' => 'Надограђен ISBN10 на ISBN13. ',
    'Removed Template redirect. ' => 'Уклоњено преусмерење шаблона. ',
    'Misc citation tidying. ' => 'Различита сређивања навода. ',
    'Use this bot]].' => 'Користи овог бота]].',
    '|Report bugs]]' => '|Пријави грешку]]',
    'Formatted ' => 'Форматиран ',
    'Suggested by' => 'Предложено од',
    'Linked from' => 'Повезано од',
    '[[Category:' => '[[Категорија:',
];

const VI_TRANS = [
    'Altered' => 'Đã thay đổi',
    'Alter:' => 'Thay đổi:',
    'URLs might have been anonymized. ' => 'Các URL có thể đã được ẩn danh. ',
    'Added' => 'Đã thêm',
    'Add:' => 'Thêm:',
    'Removed or converted URL. ' => 'Đã xóa hoặc chuyển đổi URL. ',
    'Removed URL that duplicated identifier. ' => 'Đã xóa URL trùng với định danh. ',
    'Removed access-date with no URL. ' => 'Đã xóa ngày truy cập không kèm URL. ',
    'Changed bare reference to CS1/2. ' => 'Đã chuyển đổi chú thích trần sang CS1/2. ',
    'Removed parameters. ' => 'Đã xóa tham số. ',
    'Some additions/deletions were parameter name changes. ' => 'Một số lần thêm hoặc xóa chỉ là do đổi tên tham số. ',
    'Upgrade ISBN10 to 13. ' => 'Nâng cấp ISBN10 lên ISBN13. ',
    'Removed Template redirect. ' => 'Đã xóa chuyển hướng bản mẫu. ',
    'Misc citation tidying. ' => 'Dọn dẹp chú thích khác. ',
    'Use this bot]].' => 'Sử dụng bot]].',
    '|Report bugs]]' => '|Báo lỗi]]',
    'Formatted ' => 'Định dạng ',
    'Suggested by' => 'Được đề xuất bởi',
    'Linked from' => 'Liên kết từ',
    '[[Category:' => '[[Thể loại:',
];
