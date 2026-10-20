# محرر نموذج الاشتراك

يُفتح المحرر من صفحة [النماذج](help:forms). على اليسار أربع تبويبات (**Champs** الحقول و**Design** التصميم و**Paramètres** الإعدادات و**Partager** المشاركة)، وعلى اليمين **Aperçu** (معاينة) تتحدّث تباعًا، حتى قبل الحفظ.

## الترويسة

يرافق اسمَ النموذج شارة الحالة (**Brouillon** مسوّدة أو **Actif** نشط أو **Inactif** غير نشط) وعدّادان: **Inscriptions** (الاشتراكات) و**Confirmées** (المؤكَّدة، وتخص التأكيد المزدوج).

| الزر | أثره |
| --- | --- |
| Enregistrer (حفظ) | يحفظ التعديلات؛ يكون معطّلًا ما دام لم يتغيّر شيء (وإلا تظهر عبارة «Vous avez des modifications non enregistrées.») |
| Publier (نشر) | يحفظ عند الحاجة ثم يفعّل النموذج («Formulaire publié.») ويفتح تبويب **Partager** |
| Désactiver (إيقاف) | يحلّ محل **Publier** عندما يكون النموذج نشطًا («Formulaire désactivé.») |

مغادرة الصفحة مع تعديلات غير محفوظة تُظهر رسالة تأكيد من المتصفح.

## تبويب الحقول

حقل **E-mail** (البريد) حاضر دائمًا وإلزامي. لكل حقل اضبطوا **Libellé** (التسمية، إلزامي) و**Texte indicatif** (نص إرشادي، غير موجود لحقول نعم/لا والاختيار) و**Texte d'aide** (نص المساعدة) والخانة **Obligatoire** (إلزامي). تغيّر الأسهم **Monter** / **Descendre** الترتيب، ويحذف **Retirer le champ** حقلًا. وتقترح القائمة **Ajouter un champ** [السمات](help:attributes) غير المستعملة بعد («Tous les attributs sont déjà dans le formulaire.» عند عدم بقاء أي سمة).

يضيف المفتاح **Case de consentement RGPD** خانة موافقة: اكتبوا **Texte du consentement** (إلزامي عند التفعيل) وحدّدوا بالخيار «Le consentement est obligatoire pour s'inscrire» ما إذا كان التأشير عليها شرطًا.

## تبويب التصميم

| الحقل | التفاصيل |
| --- | --- |
| Titre du formulaire وDescription | نصوص تظهر أعلى الحقول |
| Texte du bouton | إلزامي |
| Couleur principale وCouleur de fond وCouleur du texte وCouleur du texte du bouton | رموز سداسية عشرية، مثل `#0F6CBD` |
| Logo (الشعار) | عنوان (URL) لصورة منشورة على الإنترنت؛ لا يوجد رفع ملفات |
| Arrondi des angles (px) | من 0 إلى 40 |
| Police وDisposition | التخطيط **Verticale** (عمودي) أو **Horizontale** (أفقي) |

## تبويب الإعدادات

- **Nom du formulaire** (إلزامي).
- **Listes de destination** (القوائم الوجهة): [قائمة](help:lists) أو أكثر تستقبل المشتركين. إلزامي للنشر: «Sélectionnez au moins une liste avant de publier.»
- **Double opt-in** (التأكيد المزدوج): «Le contact doit confirmer son inscription par e-mail avant d'être ajouté.» يظهر إعدادان: **E-mail de confirmation** (**Par défaut** أو أحد [قوالبكم](help:templates)) و**Redirection après confirmation** (اختياري، عنوان يبدأ بـ `http://` أو `https://`). ضعوا في القالب المختار الوسم `{{ doubleoptin }}` (أو `{{ doi_link }}`) في مكان رابط التأكيد؛ ودونه يُضاف رابط في نهاية الرسالة. لا تدخل جهة الاتصال القوائم إلا بعد النقر؛ ويُرسل بريد تأكيد واحد على الأكثر لكل عنوان كل 10 دقائق.
- **Après l'inscription** (بعد الاشتراك): **Afficher un message de remerciement** (**Message de remerciement**، إلزامي) أو **Rediriger vers une URL** (عنوان كامل، إلزامي). ومع التأكيد المزدوج تُستبدل رسالة الشكر وإعادة التوجيه أعلاه بعبارة «Consultez votre boîte de réception pour confirmer votre inscription.» (ينبّه إلى ذلك إطار في التبويب): ولا تُطبَّق إلا **Redirection après confirmation** بعد النقر على رابط التأكيد.
- **Activer le captcha** (تفعيل الكابتشا): يضيف تحققًا ضد الروبوتات إلى النموذج العام. يُضبط المزوّد (hCaptcha أو Cloudflare Turnstile) ومفاتيحه مرة واحدة لكل النماذج، بواسطة مسؤول، عبر الزر **Captcha des formulaires** في صفحة [النماذج](help:forms)؛ وما دام لا مزوّد مضبوطًا فلا أثر لهذا الخيار.
- **Mettre à jour les contacts existants** (تحديث جهات الاتصال الحالية): إذا عُطّل فلن يطغى النموذج على سمات جهة اتصال معروفة (ومع التأكيد المزدوج تُستكمل القيم الفارغة فقط).
- **Prévenir ces adresses à chaque inscription** (اختياري، عنوان في كل سطر، 10 كحد أقصى): يتلقى كل عنوان بريدًا قصيرًا عند كل اشتراك جديد: اسم النموذج والقيم المُدخلة وحالة جهة الاتصال (**Contact créé** أو **Contact mis à jour** أو **En attente de confirmation**) ورابط إلى بطاقة جهة الاتصال. ومع التأكيد المزدوج يُرسَل البريد فور الاشتراك ويذكر أن جهة الاتصال لا تزال تنتظر تأكيدها. يتم الإرسال في الخلفية ولا يمنع أبدًا نجاح الاشتراك؛ والمحاولات المحجوبة (قائمة الحظر، مكافحة الاحتيال) لا تُرسل شيئًا. تابعوا الاشتراكات أيضًا من العدّادات ومن [جهات الاتصال](help:recipients).

## تبويب المشاركة

يقترح **Lien de partage rapide** (رابط المشاركة السريعة، على الشكل `/f/…`، مع **Copier** و**Ouvrir**) و**Code iframe** و**Code HTML intégrable** لتلصقوها في موقعكم. وما دام النموذج غير منشور تعرض الصفحة «Publiez le formulaire pour que ce lien et ces codes fonctionnent.»؛ وبعد **Désactiver** لا يستجيب الرابط العام.

## عند حدوث مشكلة

- **«obligatoire»** تحت حقل: أدخلوا **Libellé** أو **Texte du bouton** أو **Texte du consentement** أو **Message de remerciement** أو عنوان إعادة التوجيه بحسب التبويب المشار إليه.
- **«Saisissez une URL complète commençant par http:// ou https://.»**: صحّحوا عنوان إعادة التوجيه (بعد الاشتراك أو بعد التأكيد).
- **«Une ou plusieurs adresses e-mail sont invalides.»**: تحققوا من قائمة العناوين المراد تنبيهها.
- **المشتركون لا يظهرون في القائمة**: مع التأكيد المزدوج لا يدخلونها إلا بعد النقر على الرابط المستلم؛ قارنوا **Inscriptions** و**Confirmées**.
