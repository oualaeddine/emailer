# محرر الأتمتة

يُستعمل المحرر لبناء سيناريو [الأتمتة](help:automations): ما الحدث الذي يُدخل جهة الاتصال، ثم ما الخطوات التي تمرّ بها (رسائل، انتظار، شروط…). يُفتح بالنقر على أتمتة في القائمة، أو مباشرة بعد **Créer et ouvrir l'éditeur** (إنشاء وفتح المحرر).

## الترويسة

بجانب الاسم شارة تبيّن الحالة: **Brouillon** (مسوّدة) أو **Active** (نشطة) أو **En pause** (متوقفة مؤقتًا). وعلى اليمين:

| الزر | أثره |
| --- | --- |
| Enregistrer (حفظ) | يحفظ الاسم والمُشغِّل والإعدادات والخطوات؛ يكون معطّلًا ما دام لم يتغيّر شيء (وإلا تظهر عبارة «Vous avez des modifications non enregistrées.») |
| Activer (تفعيل) | يحفظ عند الحاجة ثم يشغّل السيناريو («Automatisation activée.») |
| Mettre en pause (إيقاف مؤقت) | يحلّ محل **Activer** عندما تكون الأتمتة نشطة («Automatisation mise en pause.») |

يبقى **Activer** غير نشط ما دام ينقص مُشغِّل مضبوط وخطوة واحدة على الأقل: «Ajoutez un déclencheur et au moins une étape pour activer l'automatisation.» وتنظّم الصفحة ثلاث تبويبات: **Flux** (المسار) و**Paramètres** (الإعدادات) و**Activité** (النشاط، ولا يتوفر إلا بعد حفظ الأتمتة).

## تبويب المسار: المُشغِّل

انقروا على بطاقة **Déclencheur** (المُشغِّل) لفتح اللوحة الجانبية واختيار الحدث.

| المُشغِّل | ما يُضبط |
| --- | --- |
| إضافة جهة اتصال إلى قائمة | **Listes** (قائمة أو أكثر، إلزامي) |
| إرسال نموذج | **Formulaires** (إلزامي)، انظروا [محرر النموذج](help:form-edit) |
| فتح حملة | **N'importe quelle campagne envoyée** (أي حملة مرسلة) أو **Des campagnes précises** (حملات محددة) |
| النقر على رابط حملة | كما سبق، إضافة إلى **URL du lien** (اختياري، يُقبل بادئة مع `*`) |
| تعديل سمة | **Attribut**، ثم **Toute modification** (أي تعديل) أو **Uniquement vers une valeur précise** (إلى قيمة محددة فقط) |
| ذكرى تاريخ | **Attribut de type date** (سمة من نوع تاريخ)، **Décalage (jours)** من -365 إلى 365 (سالب = قبل التاريخ، موجب = بعده)، **Heure d'envoi** (ساعة الإرسال) |

يضيف المفتاح **Uniquement les contacts qui remplissent ces conditions** (جهات الاتصال التي تستوفي هذه الشروط فقط) مرشّحًا على [سمات](help:attributes) جهة الاتصال. ولاستعمال «ذكرى تاريخ» أنشئوا أولًا سمة من نوع تاريخ في Contacts › Attributs. وبعد تفعيل الأتمتة يُقفَل المُشغِّل: «Le déclencheur ne peut pas être modifié tant que l'automatisation est active.»

## تبويب المسار: الخطوات

يقترح كل زر «+» بين بطاقتين (**Insérer une étape ici**) أنواع الخطوات؛ وتُفتح الخطوة فورًا في اللوحة الجانبية. وتتيح قائمة البطاقة **Modifier l'étape** (تعديل الخطوة) و**Supprimer l'étape** (حذف الخطوة).

| الخطوة | الإعدادات |
| --- | --- |
| Envoyer un e-mail (إرسال رسالة) | **Modèle d'e-mail** (إلزامي، انظروا [القوالب](help:templates))، **Expéditeur**، **Objet** (فارغ = موضوع القالب)، **Texte d'aperçu** |
| Attendre (انتظار) | **Durée** و**Unité** (minute(s) / heure(s) / jour(s))، من دقيقة واحدة إلى 365 يومًا |
| Condition (oui / non) | شروط على جهة الاتصال: من يستوفيها يتبع فرع **Oui** والبقية فرع **Non** |
| Ajouter à une liste / Retirer d'une liste | **Liste** (إلزامي) |
| Mettre à jour un attribut (تحديث سمة) | **Attribut** و**Valeur** (السمتان EMAIL وEXT_ID غير قابلتين للتعديل) |
| Fin (نهاية) | تخرج جهة الاتصال من الأتمتة؛ في آخر موضع من الفرع فقط |

الحدود: 50 خطوة على الأكثر و3 شروط متداخلة. وإذا انتهى فرعا شرط فلن تُبلَغ الخطوات التالية أبدًا (يظهر تحذير). وبعد الإرسال تعرض كل بطاقة «N entrés · N terminés» (الداخلة · المنتهية).

## المرسل وعنوان الرد

في خطوة **Envoyer un e-mail** يسرد الحقل **Expéditeur (facultatif)** [المرسلين](help:senders) لديكم. وإذا تُرك فارغًا خرجت الرسالة بعنوان حساب SMTP؛ ويعيد **Retirer l'expéditeur** إلى هذه الحالة. ويجب أن يكون المرسل المختار موثقًا للتفعيل («L'expéditeur doit être vérifié avant l'activation.»). لا يوجد في الخطوة حقل لعنوان الرد: يُستعمل **Adresse de réponse** الخاص بالمرسل المختار والمضبوط في صفحة المرسلين.

## تبويب الإعدادات

- **Nom** (إلزامي) و**Description** (اختياري).
- **Réentrée des contacts** (إعادة دخول جهات الاتصال): **Jamais** (لا تمر جهة الاتصال بالسيناريو إلا مرة واحدة) أو **Après un délai** (**Délai avant une nouvelle entrée (jours)**، من 1 إلى 3650) أو **Toujours, à chaque déclenchement** (دائمًا عند كل تشغيل).

## التفعيل والإيقاف المؤقت

عندما تكون الأتمتة نشطة يصبح تبويب **Flux** للقراءة فقط: «Mettez l'automatisation en pause pour modifier son workflow.» وفي حالة الإيقاف المؤقت لا تدخل أي جهة اتصال جديدة، وتبقى الجارية مجمّدة حتى إعادة التفعيل. ولا تتلقى جهات الاتصال في [قائمة الحظر](help:suppression) الرسائل.

## تبويب النشاط

تقترح قائمة **Période** (الفترة): **Depuis la création** (منذ الإنشاء) أو آخر 7 و30 و90 يومًا. تلخّص أربعة مؤشرات السيناريو: **Entrés** (الداخلة) و**En cours** (الجارية) و**Terminés** (المنتهية، مع النسبة) و**Sortis** (الخارجة). ويفصّل **Performance des e-mails** لكل رسالة المرسَل والمُسلَّم ونسب الفتح والنقر وإلغاء الاشتراك. ويسرد **Contacts dans l'automatisation** كل جهة اتصال مع حالتها (En cours, En attente, Terminé, Sorti, Échec) وخطوتها الحالية وتواريخها؛ ويوقفها **Faire sortir le contact** (إخراج جهة الاتصال) بعد التأكيد.

## عند حدوث مشكلة

- **«Donnez un nom à l'automatisation.»**: حقل **Nom** في تبويب **Paramètres** فارغ.
- **«Déclencheur à configurer»** أو **«Étape à compléter»**: بطاقة غير مكتملة (قائمة أو قالب أو شرط أو قيمة ناقصة). افتحوها واستكملوها.
- **«Complétez les étapes signalées avant d'enregistrer.»** / **«Corrigez les étapes signalées avant d'activer.»**: ابحثوا عن البطاقات المعلَّمة بالأحمر أو بتحذير.
- **«Entre 1 minute et 365 jours.»**: مدة خطوة **Attendre** خارج الحدود.
- **«Impossible de charger l'automatisation.»**: حدّثوا الصفحة؛ وإن كانت قد حُذفت ظهرت العبارة «Élément introuvable.».
