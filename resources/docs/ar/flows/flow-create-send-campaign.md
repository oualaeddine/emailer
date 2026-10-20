# إرسال حملة خطوة بخطوة

يتابع هذا الدليل حملة من أوّلها إلى آخرها: التحقق مما تحتاجه، وإعدادها، واختبارها، وجدولتها، ثم قراءة نتائجها. وسيكون المثال خيطاً ناظماً: يريد الفريق التجاري في PageJaunes تقديم عرض الظهور على الإنترنت للشركات الموجودة في قائمة «Prospects BTP & Construction». وستحمل الحملة اسم «Guide — campagne exemple».

## نظرة عامة

| الخطوة | أين | النتيجة |
| --- | --- | --- |
| 1 | المتطلبات | مرسل موثّق، جهات اتصال في قائمة، حساب SMTP سليم |
| 2 | الحملات › **إنشاء حملة** (Créer une campagne) | حملة بحالة **مسودة** (Brouillon) |
| 3 إلى 6 | صفحة الإعداد | اكتمال بطاقات المرسل والموضوع والمستلمين والتصميم |
| 7 | **معاينة واختبار** (Aperçu & test) | رسالة اختبار تمت مراجعتها |
| 8 | **قائمة التحقق** (Liste de vérification) | لا بند «يجب تصحيحه» |
| 9 | **جدولة** (Programmer) | إرسال فوري أو مجدول أو في أفضل وقت أو على دفعات |
| 10 | قائمة الحملات | المتابعة والإيقاف المؤقت والاستئناف |
| 11 | تقرير الحملة | النتائج |

## 1. التحقق من المتطلبات

يجب أن تتوفر ثلاثة عناصر قبل إنشاء الحملة.

| العنصر | أين تعدّه |
| --- | --- |
| **مرسل موثّق** (الاسم والعنوان في حقل «من») | [المرسلون](help:senders)، ويفضّل على [نطاق موثّق](help:domains) |
| جهات اتصال مجمّعة في **قائمة** أو **شريحة** | [القوائم](help:lists)، [الشرائح](help:segments)، وتُغذّى عبر [استيراد جهات الاتصال](help:recipients-import) |
| حساب **SMTP** واحد على الأقل بحالة جيدة | [حسابات SMTP](help:smtp) |

من دون مرسل موثّق تعرض بطاقة المرسل العبارة «Aucun expéditeur vérifié. Ajoutez-en un dans Paramètres › Expéditeurs.». ومن دون حساب SMTP متاح يمكن جدولة الحملة لكن لن تنطلق أي رسالة (انظر «عند حدوث مشكلة»).

المحتوى ليس شرطاً مسبقاً: ستنشئونه أو تختارونه من [القوالب](help:templates) أثناء الإعداد.

## 2. إنشاء الحملة

من القائمة، ضمن مجموعة **Marketing**، انقروا على **E-mails**. تعرض صفحة **Campagnes e-mail** حملاتكم مع تبويبات للحالة (Toutes، Brouillons، Programmées، En cours، Envoyées، Suspendues، Archivées).

![قائمة حملات البريد](/docs/screenshots/campaign-flow/01-campaigns-list.png)

1. انقروا على **Créer une campagne** (أعلى اليمين).
2. اختاروا النوع: **E-mail** (إرسال كلاسيكي) أو **Test A/B** (موضوعان أو محتويان، وتُرسل النسخة الأفضل تلقائياً). في هذا الدليل: **E-mail** ثم **Continuer**.
3. اكتبوا **Nom interne de la campagne** (لا يراه سواكم وفريقكم)، مثل `Guide — campagne exemple`.
4. انقروا على **Créer la campagne**.

![نافذة «إنشاء حملة»](/docs/screenshots/campaign-flow/02-create-dialog.png)

تصلون إلى صفحة الإعداد. الحملة موجودة فعلاً بحالة **مسودة**: يمكنكم مغادرتها في أي وقت عبر **Enregistrer et quitter** أو سهم العودة، ثم إعادة فتحها بالنقر على اسمها في القائمة.

## 3. فهم صفحة الإعداد

![صفحة إعداد الحملة](/docs/screenshots/campaign-flow/03-setup.png)

تعرض الترويسة الاسم (القلم لإعادة التسمية) والحالة وثلاثة إجراءات: **Enregistrer et quitter** و**Aperçu & test** و**Programmer**. يبقى الزر الأخير معطّلاً ما دامت الخطوات الإلزامية غير مكتملة (تلميح: «Complétez les étapes obligatoires pour programmer la campagne.»).

وتحتها بطاقات تُستكمل بالترتيب: **Expéditeur** (المرسل) و**Objet** (الموضوع) و**Destinataires** (المستلمون) و**Design** (التصميم) و**Test A/B** (اختياري) و**Paramètres supplémentaires** (الإعدادات الإضافية). تعرض كل بطاقة علامة خضراء عند اكتمالها. وعلى اليمين تتحدث **Liste de vérification** بعد كل حفظ.

تُفتح كل بطاقة بزرها («Sélectionner l'expéditeur»، «Ajouter un objet»…) وتُحفظ بزر **Enregistrer** الخاص بها.

## 4. المرسل والموضوع

**المرسل**

1. انقروا على **Sélectionner l'expéditeur** واختاروا هوية من القائمة (لا تُقترح إلا المرسلون الموثّقون؛ ويقود الرابط **Gérer les expéditeurs** إلى صفحتهم).
2. اختياري: **Adresse de réponse**. إن تُركت فارغة فإن عنوان المرسل هو الذي يتلقى الردود.
3. **Enregistrer**.

**الموضوع**

1. انقروا على **Ajouter un objet**.
2. اكتبوا **Objet**: `Boostez la visibilité de {{ contact.SOCIETE }} avec PageJaunes`. يبيّن عدّاد عدد الأحرف؛ وفوق 60 حرفاً يظهر تنبيه بأن الموضوع قد يُقتطع (الحد الأقصى 255).
3. اختياري: **Texte d'aperçu**، السطر الرمادي الذي يظهر بعد الموضوع في صندوق الوارد. تعرض الكتلة «Aperçu dans la boîte de réception» النتيجة.
4. **Enregistrer**.

### التخصيص بالوسوم

يُستبدل الوسم، لكل مستلم، ببيان من بطاقته. تُدرج الأيقونتان على يمين الحقول وسماً (أيقونة **Personnaliser**) أو رمزاً تعبيرياً (**Insérer un emoji**). الصيغة:

| الوسم | النتيجة |
| --- | --- |
| `{{ contact.PRENOM }}` و`{{ contact.NOM }}` و`{{ contact.SOCIETE }}` و`{{ contact.EMAIL }}` | بيانات بطاقة جهة الاتصال |
| `{{ contact.NOM_DE_L_ATTRIBUT }}` | أي [سمة](help:attributes) مخصّصة (مثل `{{ contact.wilaya }}`) |
| `{{ contact.PRENOM \| default:"cher client" }}` | قيمة بديلة عندما يكون الحقل فارغاً |
| `{{ unsubscribe }}` | رابط إلغاء الاشتراك |
| `{{ mirror }}` و`{{ update_profile }}` | النسخة على الويب، تحديث الملف الشخصي |
| `{{ campaign.NAME }}` و`{{ sender.NAME }}`… | اسم الحملة، اسم المرسل… |

تعمل الوسوم في الموضوع ونص المعاينة والمحتوى وحقل «À». أما الوسم المجهول (خطأ طباعي، أو `{{ prenom }}` بدل `{{ contact.PRENOM }}`) فيمنع الجدولة: انظروا قائمة التحقق.

## 5. المستلمون

1. انقروا على **Ajouter des destinataires**. تُفتح نافذة **Choisir les destinataires**.
2. في **Envoyer à** حددوا [قوائم](help:lists) (تبويب **Listes**) و/أو [شرائح](help:segments) (تبويب **Segments**). يساعد حقل البحث على إيجاد الاسم. الحد الأقصى: 50 قائمة وشريحة.
3. اختياري: في **Exclure** حددوا ما يجب ألا يُستهدف (مثل عملائكم النشطين).
4. تبقى العناصر المحددة ظاهرة على شكل شارات فوق القائمة (انقروا على الإشارة × لإزالة أحدها). وتتحدث كتلة **Audience estimée** مباشرة: عدد المستلمين والمستبعدين والعناوين «en liste noire» (جهة اتصال موجودة في عدة قوائم تُحسب مرة واحدة؛ ومن كان في قائمة مستبعَدة يُزال حتى لو ورد في القائمة المختارة). وهذه الأخيرة، أي عناوين [قائمة الحظر](help:suppression)، تُستبعد دائماً. ويظهر تنبيه إذا لم تفتح نسبة كبيرة من الجمهور أي رسالة منذ 90 يوماً.
5. اختياري: **Champ « À »** لتخصيص الاسم المعروض للمستلم (مثل `{{ contact.PRENOM }} {{ contact.NOM }}`).
6. **Enregistrer**.

![اختيار المستلمين](/docs/screenshots/campaign-flow/04-recipients.png)

في المثال: قائمة «Prospects BTP & Construction»، 130 جهة اتصال، منها 13 في قائمة الحظر، أي 117 مستلماً.

## 6. التصميم

1. في بطاقة **Design** انقروا على **Commencer la conception**. تقترح نافذة **Choisir un modèle** خمسة تبويبات: **Galerie** (قوالب جاهزة، منها **Modèle vierge**) و**Mes modèles** و**Campagnes précédentes** و**Coder** (HTML) و**Éditeur de texte enrichi**.
2. انقروا على قالب، مثل **Lancement de produit**: يُفتح محرر السحب والإفلات.
3. عدّلوا المحتوى: اسحبوا كتلاً من **Contenu › Blocs** (عنوان، نص، صورة، زر، فاصل، أعمدة…) أو أقساماً جاهزة (**Sections**)، واضبطوا الكتلة المحددة في لوحة **Propriétés**. وفي كتلة النص تُدرج قائمة **Personnaliser…** وسماً (Prénom، Société، Lien de désinscription…) وتضع المؤشر بعده مباشرة؛ ويكون المدقق الإملائي للمتصفح معطّلاً في لوحة التحرير. وفي الشريط العلوي أصبح **Restaurer une version précédente** أيقونة (تلميح عند التمرير).
4. انقروا على **Enregistrer et quitter** للعودة إلى الإعداد (ويُبقي **Enregistrer** المحرر مفتوحاً).

![محرر التصميم](/docs/screenshots/campaign-flow/05-design-editor.png)

تعرض البطاقة عندئذٍ صورة مصغّرة وثلاثة إجراءات: **Modifier le design** و**Changer de modèle** و**Aperçu**. تفاصيل المحرر في [محرر التصميم](help:campaign-design).

**رابط إلغاء الاشتراك إلزامي.** يجب أن يحتوي التصميم على رابط لإلغاء الاشتراك: إما الوسم `{{ unsubscribe }}` (قائمة **Personnaliser…** › «Lien de désinscription»)، أو رابط إلى عنوان `/unsubscribe/…`. وتحتوي قوالب المعرض على رابط جاهز. ومن دونه تُمنع الجدولة.

## 7. الإعدادات الإضافية واختبار A/B (اختياريان)

تضم بطاقة **Paramètres supplémentaires** (زر **Modifier les paramètres**) خصوصاً:

- **Page de désinscription**: **Page par défaut** أو **Page personnalisée** (انظروا [صفحات إلغاء الاشتراك](help:unsubscribe-pages))؛
- **Tags** و**Dossier** لإيجاد الحملة بسهولة في القائمة؛
- **Suivi Google Analytics (UTM)**: يضيف **Activer le suivi UTM** معاملات `utm_*` إلى الروابط؛
- **Suivi des ouvertures et des clics** (مفعّل افتراضياً) و**Lien miroir** («voir dans le navigateur»).

وتخدم بطاقة **Test A/B** (زر **Activer le test A/B**) مقارنة نسختين: العنصر المختبَر (**Objet** أو **Contenu**)، وحجم العيّنة (من 5 إلى 50 % من المستلمين)، ومعيار الفائز (معدل الفتح أو النقر)، ومدة الاختبار، والإرسال التلقائي للنسخة الفائزة إلى بقية الجمهور. ويجب أن تكون مواضيع (أو محتويات) النسختين A وB مختلفة.

في الإرسال الأول يمكنكم ترك بقية الإعدادات كما هي.

## 8. الاختبار قبل الإرسال

انقروا على **Aperçu & test** في الترويسة: تُفتح لوحة على اليمين.

![المعاينة والاختبار](/docs/screenshots/campaign-flow/06-preview-test.png)

1. راجعوا «Aperçu dans la boîte de réception» ثم العرض في وضع **Ordinateur** (الحاسوب) و**Mobile** (الهاتف).
2. في **Adresses de test** اكتبوا من 1 إلى 5 عناوين مفصولة بفواصل، ثم انقروا على **Envoyer un test**. تظهر الرسالة «E-mail de test envoyé.» مع «Dernier test envoyé le …».
3. افتحوا الرسالة المستلمة: تحققوا من الموضوع والصور والروابط ونسخة الهاتف.
4. عند تفعيل اختبار A/B يبدّل المحدِّد **Version du test A/B** (Version A، Version B) المعاينة والإرسال التجريبي بين النسختين.

للعلم:

- **الحد**: 5 عناوين في كل إرسال و10 إرسالات تجريبية في الساعة لكل حملة.
- تُملأ الوسوم ببطاقة جهة الاتصال **إذا طابق عنوان الاختبار جهة اتصال موجودة**. وإلا بقيت فارغة أو أخذت قيمتها `default:`: استعملوا عنوان جهة اتصال تجريبية للتحقق من التخصيص.
- لا يُحتسب الاختبار في الإحصاءات، ولا يحمل ترويسات إلغاء الاشتراك الخاصة بالإرسال الفعلي. ولا يُستعمل عنوان الاختبار المجهول مستلماً لحملاتكم أبداً.
- يمرّ الاختبار عبر حسابات SMTP لديكم، كأي رسالة حقيقية.

## 9. قراءة قائمة التحقق

تفحص **Liste de vérification**، على يمين الصفحة، الحملة قبل الإرسال.

![قائمة التحقق](/docs/screenshots/campaign-flow/07-checklist.png)

| البند | مانع؟ | ما الذي يُفحص |
| --- | --- | --- |
| Expéditeur vérifié | نعم | اختيار مرسل موثّق |
| Destinataires sélectionnés | نعم | مستلم واحد على الأقل بعد خصم الاستبعادات وقائمة الحظر |
| Objet renseigné | نعم | الموضوع غير فارغ |
| Design prêt | نعم | التصميم غير فارغ |
| Balises de personnalisation | نعم | لا وسم مجهول (الرسالة: «Balises inconnues : …») |
| Lien de désinscription présent | نعم | انظروا الخطوة 6 |
| E-mail de test envoyé | لا | إرسال اختبار واحد على الأقل |
| Liens valides | لا | نتيجة زر **Vérifier les liens** |
| Taille de l'e-mail | لا | تنبيه فوق 102 كيلوبايت (خطر اقتطاع من Gmail) |
| Score de spam | لا | «Analyse anti-spam non disponible» |

تحمل كل بند شارة: **OK** أو **Recommandé** (نصيحة غير مانعة) أو **À corriger** (يجب تصحيحه) أو **En cours** (الفحص جارٍ) أو **Non vérifié** (لم يُفحص). وتبيّن الشارة العامة في الأعلى:

- **Bloquée** (محجوبة): بند إلزامي واحد على الأقل «À corriger». زر **Programmer** غير فعّال.
- **Incomplète** (غير مكتملة): لا شيء يمنع، لكن تبقى نصيحة (لم يُرسل اختبار، لم تُفحص الروابط…). تصبح الرسالة تحت العنوان «Vous pouvez programmer l'envoi ; quelques vérifications recommandées restent à faire» (يمكنكم جدولة الإرسال؛ وتبقى بعض الفحوص الموصى بها)، ويبقى زر **Programmer** فعّالاً.
- **Prête** (جاهزة): كل شيء أخضر («Votre campagne est prête à être envoyée»).

انقروا على **Vérifier les liens** للتأكد من استجابة كل رابط في التصميم؛ ويُبلَّغ عن الرابط المعطّل («n lien(s) inaccessible(s)»). هذا الفحص نصيحة: لا يمنع الإرسال.

## 10. الجدولة أو الإرسال

انقروا على **Programmer**. تُفتح نافذة **Confirmer l'envoi**: يعرض **Récapitulatif** كلاً من **De** و**À** (مع عدد المستلمين) و**Objet** و**Envoi prévu**. راجعوه ثم اختاروا من **Options d'envoi**.

![تأكيد الإرسال](/docs/screenshots/campaign-flow/08-schedule.png)

| الخيار | طريقة العمل | الزر الأخير |
| --- | --- | --- |
| **Envoyer maintenant** (إرسال الآن) | تنطلق الحملة فوراً. | **Envoyer maintenant** |
| **Programmer pour plus tard** (جدولة لوقت لاحق) | **Date** و**Heure** و**Fuseau horaire** (Africa/Algiers افتراضياً) إلزامية؛ ويجب أن يكون التاريخ في المستقبل. الافتراضي: غداً 9:00. | **Programmer** |
| **Envoyer au meilleur moment** (في أفضل وقت) | يتلقى كل مستلم الرسالة في الساعة التي يفتح فيها رسائله عادةً، خلال 24 ساعة. يستند التحليل إلى آخر 10 حملات له؛ ودون سجل تُستعمل الساعة الأفضل أداءً في الحساب. يبيّن ملخّص عدد جهات الاتصال التي لها سجل. التاريخ والساعة اختياريان. | **Programmer au meilleur moment** |
| **Envoyer par lots** (على دفعات) | يُوزَّع الإرسال على الوقت: **Nombre d'e-mails par lot** (من 50 إلى 100 000، والافتراضي 500) كل **Intervalle entre les lots (minutes)** (من 5 إلى 1 440، والافتراضي 30). التاريخ والساعة اختياريان. | **Programmer l'envoi par lots** |

قواعد يجب معرفتها:

- في «أفضل وقت» و«على دفعات» يعني ترك التاريخ والساعة فارغين «ابدأ فوراً»؛ ومع تاريخ تنتظر الحملة ذلك الموعد. يحتفظ كل خيار بحقوله الخاصة فقط: عند الانتقال من «Programmer pour plus tard» إلى «أفضل وقت» أو «على دفعات» يُمسح التاريخ الافتراضي المقترح، ولا يعرض سطر **Envoi prévu** تاريخاً إلا إذا أدخلتموه. وتتمرّر النافذة من الداخل مع بقاء العنوان والأزرار ظاهرين.
- **لا تقترح الحملة ذات اختبار A/B سوى «Envoyer maintenant» و«Programmer pour plus tard»**: إذ يجب أن تنطلق العيّنة دفعة واحدة ليُختار الفائز على بيانات كاملة.
- إذا كان التاريخ في الماضي يتحول حقلا Date وHeure إلى حالة الخطأ؛ وإذا خرج حجم الدفعة أو الفاصل عن الحدود يُشار إلى الحقل المعني.
- يعيد الخادم فحص قائمة التحقق: فإذا تغيّر بند مانع في الأثناء تعرض النافذة «La campagne ne peut pas être programmée : …» مع البنود المعنية.

بعد التأكيد تظهر رسالة («Campagne programmée.» أو «Envoi de la campagne lancé.») وتعودون إلى القائمة، في تبويب **Programmées** أو **En cours**. ولا تعود الحملة قابلة للتعديل ما دامت قيد الإرسال.

## 11. متابعة الإرسال

### الحالات

| الحالة | المعنى |
| --- | --- |
| **Brouillon** (مسودة) | قيد الإعداد، لم تُرسل أبداً. |
| **Programmée** (مجدولة) | مسجَّل لها تاريخ إرسال مستقبلي. وفي الموعد تنتقل إلى «En cours». |
| **En cours** (جارية) | تُسلَّم الرسائل إلى محرك الإرسال على دفعات. وتبدأ «أفضل وقت» و«على دفعات» مباشرة بحالة «En cours» عندما لا يُحدَّد تاريخ. |
| **En pause** (متوقفة مؤقتاً) | لا تُحرَّر أي رسالة جديدة. |
| **Suspendue** (معلّقة) | الإرسال متوقف؛ ويذكر تلميح الحالة السبب (استنفاد حصة الإرسال، عدم توفر حساب SMTP…). يعيد **Reprendre** تشغيل الإرسال. |
| **Envoyée** (مُرسلة) | عولج جميع المستلمين. |
| **Annulée** (ملغاة) | أُوقف الإرسال نهائياً. |
| **Archivée** (مؤرشفة) | حملة مرتّبة خارج تبويب «Toutes». |

الإرسال ليس لحظياً: تمر الرسائل عبر حسابات SMTP لديكم، مع احترام حصصها وحدودها لكل نطاق. وقد يستغرق جمهور من مئات جهات الاتصال بعض الوقت.

### إجراءات قائمة «Plus d'actions» (أيقونة ⋯)

| الحالة | الإجراءات المقترحة |
| --- | --- |
| Brouillon | Modifier، Dupliquer، Envoyer un test، Programmer، Envoyer maintenant، Supprimer |
| Programmée | Modifier، Dupliquer، Envoyer un test، Déprogrammer، Envoyer maintenant، Annuler l'envoi |
| En cours | Rapport، Dupliquer، Mettre en pause، Annuler l'envoi |
| En pause | Rapport، Dupliquer، Reprendre، Annuler l'envoi |
| Suspendue | Rapport، Modifier، Dupliquer، Reprendre، Annuler l'envoi، Archiver |
| Envoyée | Rapport، Dupliquer، Envoyer aux nouveaux contacts، Archiver |
| Annulée | Dupliquer، Archiver، Supprimer (وRapport إذا انطلقت رسائل) |

- **Mettre en pause** (إيقاف مؤقت): دون تأكيد؛ تنطلق الرسائل المسلَّمة فعلاً إلى محرك الإرسال، ويُحتجز ما تبقّى. ويواصل **Reprendre** من النقطة التي توقف عندها الإرسال.
- يطلب **Annuler l'envoi** تأكيداً («Annuler cette campagne ?») ولا يسترجع أبداً رسالة انطلقت؛ إذ يُستبعد فقط المستلمون الذين لم تصلهم الرسالة بعد.
- يعيد **Déprogrammer** الحملة إلى مسودة لتعديلها ثم جدولتها من جديد. والحملة «Programmée» المعدَّلة يجب أن تستوفي دائماً قائمة التحقق.
- تُنشئ **Dupliquer** مسودة جديدة «Copie de …» بالإعدادات والتصميم نفسيهما ودون تاريخ: وهي أسرع طريقة لإعادة إطلاق عملية ناجحة.
- ينشئ **Envoyer aux nouveaux contacts** (حملة «Envoyée») حملة جديدة موجّهة إلى جهات الاتصال المضافة إلى القوائم منذ الإرسال.

### إلغاء الاشتراك

تحمل كل رسالة في حملة فعلية الترويستين `List-Unsubscribe` و`List-Unsubscribe-Post` (إلغاء اشتراك بنقرة واحدة تعرضه Gmail وOutlook…)، إضافة إلى رابط التصميم. يصل المستلم إلى صفحة إلغاء الاشتراك (الافتراضية أو المخصّصة)؛ ثم يُضاف عنوانه إلى [قائمة الحظر](help:suppression) (السبب «Désabonnement manuel»، أو «Désabonnement global (tout)» إن اختار ترك كل شيء) ولن يتلقى بعدها أي حملة.

## 12. قراءة النتائج

في تبويب **Envoyées** انقروا على اسم الحملة (أو ⋯ › **Rapport**) لفتح تقريرها.

![تقرير الحملة](/docs/screenshots/campaign-flow/09-report.png)

خمسة تبويبات: **Vue d'ensemble** (نظرة عامة: Envoyés، Délivrés، Ouvertures uniques، Clics uniques، Désinscriptions، Plaintes، Rebonds، مقارنة بمتوسط حسابكم) و**Délivrabilité** و**Ouvertures** و**Clics** و**Désinscriptions**. وتكمل الصفحةَ الأزرار **Exporter** و**Comparer** و**Voir l'e-mail**.

انتظروا من 48 إلى 72 ساعة قبل الاستنتاج: إذ تمتد الفتحات والنقرات على عدة أيام. وتُشرح القراءة المفصّلة في [تحليل النتائج](help:flow-analyze-results) و[تقرير الحملة](help:campaign-report).

## عند حدوث مشكلة

- **زر Programmer معطّل**: بطاقة إلزامية غير مكتملة، أو قائمة التحقق «Bloquée». مرّروا المؤشر على الزر ثم عالجوا البنود «À corriger» في القائمة.
- **«Aucun expéditeur sélectionné» / «L'expéditeur n'est pas vérifié»**: اختاروا مرسلاً موثّقاً؛ وإلا فوثّقوه في [المرسلون](help:senders).
- **«Aucun destinataire»**: القوائم فارغة، أو كل العناوين مستبعدة أو في قائمة الحظر. تحققوا من **Audience estimée**.
- **«Balises inconnues : {{ prenom }}»**: صحّحوا الوسم (`{{ contact.PRENOM }}`) في الموضوع أو نص المعاينة أو «À» أو التصميم. والوسم `{{ first_name }}` غير موجود أيضاً.
- **«Le design ne contient pas de lien de désinscription»**: أدرجوا `{{ unsubscribe }}` (قائمة **Personnaliser…**) أو ابدأوا من قالب في المعرض.
- **«La campagne doit avoir un objet et un contenu avant l'envoi d'un test.»**: أكملوا الموضوع والتصميم قبل الاختبار.
- **«Vous pouvez saisir au maximum 5 adresses.»** / **«Limite de 10 envois de test par heure atteinte pour cette campagne.»**: قلّصوا القائمة، أو انتظروا الساعة التالية.
- **«La campagne ne peut pas être programmée : … »**: ظهر بند مانع بين فتح النافذة والتأكيد؛ أغلقوا النافذة وأعيدوا قراءة قائمة التحقق.
- **«Cette campagne doit être approuvée avant de pouvoir être envoyée.»**: الخيار **Approbation requise avant l'envoi** مفعّل في **Paramètres supplémentaires**. ألغوا تأشيره إن لم تكن لديكم دورة موافقة.
- **«Seules les campagnes en brouillon peuvent être programmées.»**: سبق أن أُطلقت الحملة أو جُدولت (مثلاً من تبويب آخر). أعيدوا تحميل القائمة.
- **يبقى Programmer معطّلاً رغم أن كل شيء أخضر**: اختبار A/B مفعّل لكنه غير مكتمل. افتحوا بطاقة **Test A/B** وصحّحوها («Les objets des versions A et B doivent être différents.»، «Les versions A et B du test A/B sont obligatoires.»)، أو عطّلوا الاختبار.
- **تبقى الحملة «En cours» دون أن تنطلق أي رسالة**: لا يتوفر أي حساب SMTP (معطّل أو استُنفدت حصته). تُعاد محاولة الرسائل كل 5 دقائق؛ تحققوا من [حسابات SMTP](help:smtp).
- **ارتدادات كثيرة منذ الدقائق الأولى**: أوقفوا الحملة مؤقتاً ثم تحققوا من جودة القائمة. فالإرسال الجماعي إلى عناوين غير صالحة يُضعف قابلية التسليم على المدى الطويل.
- **المحتوى المرسَل ليس الصحيح**: الحملة الجارية لم تعد قابلة للتعديل. ألغوا الإرسال، وضاعفوا الحملة، وصحّحوا التصميم ثم أعيدوا الإطلاق.
- **عدد المستلمين أقل من عدد القائمة**: تُحذف عناوين [قائمة الحظر](help:suppression) والتكرارات والاستبعادات قبل الإرسال.
