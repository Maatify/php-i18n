# معيار دورة حياة الوثائق

## بيانات المعيار

- **Standard ID:** `std-documentation-lifecycle`
- **Standard Version:** `3.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`
- **اللغة المعتمدة:** العربية.
- **النطاق:** ملكية الوثائق المعيارية، وحدود السلطة بينها، ودلالات الحالة الحالية والتاريخية، ودورة حياتها بعد إغلاق العمل، ومراجعة حداثتها، والاحتفاظ بها.

كانت `1.0.0` **OWNER-APPROVED BOOTSTRAP VERSION** لهذه الهوية canonical الجديدة آنذاك، ولم تكن لها lineage سابقة.

## 1. Applicability

ينطبق هذا المعيار على نطاقات Maatify التي تملك أو تعتمد وثائق دائمة أو governance/release documentation، بما في ذلك:

- مستودع Standalone Package القابل لإعادة الاستخدام والتوزيع؛
- Artifact Root لموديول Base قابل للاستخراج عندما يملك ملفات الوثائق والعقود المعنية؛ و
- نطاق Repository Governance الذي يملك ADRs أو Blueprints أو Verification evidence أو وثائق الحالة الحالية أو سجلات الاعتماد.

تملك هذه Standard applicability الخاصة بدورة حياة الوثائق عندما يكون الـScope مطالبًا بتمييز الوثيقة الحالية من التاريخية، أو تحديد authority والاحتفاظ والحداثة، أو إدارة وثائق release/consumer الدائمة. لا يجعل هذا الانطباق `STANDARD_VERSIONING_POLICY_AR.md` Applicable Engineering Standard لمجرد وجودها في `governance/`، ولا يفرض نسخ وثائق التاريخ أو التنفيذ إلى Consumer Repository.

لا ينطبق هذا المعيار على نطاق code-only لا يملك وثائق دائمة أو governance/release documentation؛ ولا يوسع Profile أو Manifest نطاقه، بل يظل هذا التعريف canonical applicability الذي تستخدمه Adoption Stage 2.

## 2. الملكية وحدود السلطة

لكل concern توثيقي معياري مالك canonical واحد. تشير الوثائق الأخرى إلى المالك ولا تعيد كتابة العقد نفسه.

| Concern | المالك canonical |
|---|---|
| العقد العام المستقر للحزمة ومرجع Public API | Package Reference المملوك لمعيار بناء الحزمة |
| Current Project Technical Design / Architecture في Host Project | `docs/project/DEVELOPER_REFERENCE.md` المملوك لمعيار Project Documentation |
| Current Project Operational/System Behavior في Host Project | `docs/project/SYSTEM_REFERENCE.md` المملوك لمعيار Project Documentation |
| Current Design / Runtime في Other Applicable Scopes | وثيقة Architecture أو Lifecycle الحالية الخاصة بالنطاق، حسب الانطباق |
| تغطية Project Developer/System References وتفاصيلهما | `PROJECT_DOCUMENTATION_STANDARD_AR.md` |
| القرار وسياقه ومبرراته ونتائجه | ADR |
| طريقة الاستخدام والتكامل | Usage Guide أو Integration Guide |
| عرض README وUsage Guide و`examples/` والشارات وCHANGELOG وRelease Notes | Library Presentation Standard |
| qualification الخاصة بـrelease SHA وأدلة تنفيذ CI | CI Workflow Standard |
| دورة Phase وBatch وIntegration | GitHub Phase Stack Workflow |
| الاعتماد والتكوين وحل applicability | Standards Adoption Standard |
| دلالات دورة حياة الوثيقة والحداثة والاحتفاظ | هذا المعيار |

هذا المعيار لا يملك Layout الخاص بـREADME، أو Composer metadata، أو ميكانيكا CI، أو معمارية runtime، أو دورة Phase، أو ميكانيكا الاختبار، أو عرض CHANGELOG وRelease Notes. لا ينقل حدود الملكية هذه إلى وثيقة أخرى ولا ينشئ contract تقنيًا بديلًا.

## 2.1 لغة Durable Repository Documentation

هذا المعيار هو المالك canonical لقاعدة لغة **Durable Repository Documentation**. تكون الوثائق التقنية الدائمة الموجهة إلى developers أو package consumers أو maintainers أو system/operators **بالإنجليزية افتراضيًا**، لأن اللغة هنا تتحدد بدور الوثيقة وجمهورها المستهدف، لا بلغة المعيار الذي يحكمها أو باسم الملف وحده.

ينطبق ذلك، عند انطباق دور الوثيقة، على README وPackage Reference وDeveloper/System References وUsage/Integration Guides وArchitecture وOperational/System technical guides وCHANGELOG وSECURITY وCONTRIBUTING وغيرها من الوثائق التقنية الدائمة. لا تنشئ هذه الأمثلة schema أو قائمة مغلقة؛ يبقى الدور الفعلي للوثيقة هو معيار التصنيف.

يُستثنى من ذلك artifact المعلن صراحةً كعربي في اسمه أو Metadata أو canonical ownership، بما في ذلك ملفات `*_AR.md`، أو الذي يفرضه Owner-approved project requirement صريح. وجود Standard مكتوبة بالعربية لا يجعل artifacts التي تحكمها عربية تلقائيًا. وتظل technical identifiers بصيغتها التقنية الأصلية.

هذه القاعدة تخص Repository Documentation الدائمة، ولا تغيّر قاعدة العربية الافتراضية للتواصل والتنفيذ وPrompts والتقارير ونتائج المراجعة ووصف وملخص Pull Request المملوكة لـ`AI_COLLABORATION_WORKFLOW_AR.md`. كما لا تمنح تفويضًا لترجمة ملفات موجودة أو historical documentation تلقائيًا؛ يظل أي تحديث أو ترحيل لاحق خاضعًا لنطاقه واعتماده المستقل.

## 2.2 لغة PHP Source-Code Documentation

هذا المعيار هو المالك canonical للغة **PHP Source-Code Documentation** داخل ملفات PHP التي يملكها المستودع وتقع ضمن نطاق انطباق هذا المعيار. تكون هذه التوثيقات **بالإنجليزية افتراضيًا**، ولا تحددها لغة الـPrompt أو الـStandard أو تقرير التنفيذ أو المحادثة أو أي Artifact من Artifacts التواصل والتنفيذ والتعاون.

يشمل هذا العقد، عندما يكون النص توثيقًا موجّهًا إلى المطورين أو الصيانة، ما يلي:

- PHPDoc وDocBlocks الخاصة بالـclass والـinterface والـtrait والـenum والـmethod والـfunction والـproperty؛
- inline comments وblock comments داخل ملفات PHP؛
- تعليقات `TODO` و`FIXME` الموجهة إلى المطورين؛ و
- التعليقات داخل اختبارات PHP متى كانت Source-Code Documentation.

لا يفرض هذا العقد تعريب أو ترجمة أسماء الـclass أو الـinterface أو الـtrait أو الـenum أو الـmethod أو الـfunction أو الـproperty أو أي technical identifier آخر. كما لا ينشئ عقدًا عامًا للغات برمجة غير PHP.

### الفصل عن Runtime / User-Facing Content

لا تعني قاعدة الإنجليزية الخاصة بـPHP Source-Code Documentation أن **Runtime / User-Facing Content** يجب أن يكون بالإنجليزية. تبقى اللغة العربية أو أي لغة أخرى مسموحة عندما يفرضها العقد الوظيفي للمحتوى، بما في ذلك UI text وlocalized strings ورسائل التحقق أو الخطأ الموجهة للمستخدم والبريد الإلكتروني وSMS والإشعارات وموارد الترجمة وbusiness content/data ونصوص استجابات runtime الموجهة للمستخدم.

مثلًا، لا يكون النص التالي مخالفًا لمجرد أنه عربي إذا كان رسالة موجهة للمستخدم ومقصودة بهذه اللغة:

```php
throw new ValidationException('رقم الهاتف غير صحيح');
```

وعليه، فإن لغة **Durable Repository Documentation** ولغة **PHP Source-Code Documentation** ولغة **Runtime / User-Facing Content** مجالات منفصلة؛ ولا تنقل إحداها قاعدتها إلى الأخرى. لا يشمل هذا العقد `vendor/` أو third-party source أو generated code غير المملوك للمشروع، ما لم يعامله المشروع صراحةً كـsource maintained يدويًا.

يثبت هذا القسم العقد canonical فقط؛ ولا ينشئ Adoption Upgrade تلقائيًا أو remediation تلقائية في Consumer Repositories، ولا يفرض بهذه المهمة تحويل التعليقات التاريخية في المستهلكين.

## 3. أدوار الوثائق

- **Package Reference:** العقد العام الحالي والمستقر للمستهلك. يُحدّث in-place ولا يتحول إلى يوميات تاريخية.
- **Project Developer/System References:** مراجع current-state الخاصة بـHost Project، وتملك `PROJECT_DOCUMENTATION_STANDARD_AR.md` تفاصيل أدوارهما وتغطيتهما وبنيتهما. تُحدّث in-place ولا تتحول إلى changelog أو historical diary.
- **وثيقة Architecture أو Lifecycle الحالية:** تصف التصميم أو التشغيل المدعوم حاليًا في Other Applicable Scopes. أما داخل Host Project فتكون supporting/detail document مرتبطة بالـProject Reference المناسبة، ولا تملك current Project state بصورة مستقلة عنها.
- **ADR:** تحفظ القرار وسياقه ومبرراته ونتائجه وتاريخه. لا يُعاد كتابة الحقيقة التاريخية، ويُذكر supersession أو deferred state عند الحاجة.
- **Blueprint:** أداة تخطيط وتنفيذ أثناء العمل. لا تكون مصدر runtime الحالي بعد إغلاقها.
- **Roadmap:** تخطيط وحالة وتسلسل فقط، وليست عقد runtime.
- **Verification evidence/report:** دليل مرتبط بنقطة زمنية وSHA وscope محددة، ولا يثبت أي HEAD لاحقة تلقائيًا.
- **Audit:** تقييم لنقطة زمنية محددة؛ وقد يظل finding صحيحًا تاريخيًا بعد remediation.
- **README:** ملخص consumer-facing، وليست العقد العام الكامل.
- **CHANGELOG وRelease Notes:** تاريخ وعرض لتغييرات الإصدارات، وليستا عقد runtime الحالي.

تنتقل النتائج الدائمة إلى المالك الصحيح:

```text
Public Contract → Package Reference
Host Project Current Technical Design / Architecture → Project Developer Reference
Host Project Current Operational/System Behavior → Project System Reference
Other Applicable Scope Current Design / Runtime → Architecture or Lifecycle document as applicable
Decision Rationale → ADR
Usage → How-to/Integration Guide
Release Delta → CHANGELOG/Release Notes
```

## 4. الحالي والتاريخي والمؤقت

يجب التمييز صراحةً بين:

- صياغة ADR التاريخية الصحيحة؛
- وصف Architecture أو runtime الحالي المتقادم؛
- capability مؤجلة فعلًا؛
- دليل تحقق تاريخي مرتبط بفرع أو SHA قديم؛
- صياغة عابرة تخص branch أو PR أو executor ولا يجوز تحويلها إلى contract دائم؛
- إصدارات أو domains أو روابط متقادمة.

لا تُعاد كتابة السجلات التاريخية لتبدو current. عند تغير الواقع الحالي، تُحدّث وثيقة الحالة الحالية أو يُضاف سجل قرار يوضح الانتقال، مع إبقاء الحقيقة التاريخية كما كانت.

تظل تفاصيل required coverage وproject-level self-sufficiency الخاصة بـProject Developer/System References مملوكة لـ`PROJECT_DOCUMENTATION_STANDARD_AR.md`. يملك هذا المعيار semantics الخاصة بالحالي والتاريخي وfreshness وretention وdocument lifecycle، ولا يعيد نسخ Contract التغطية المملوك لمعيار Project Documentation.

## 5. دورة الحياة بعد الإغلاق والحداثة

بعد إغلاق Phase أو Work Unit أو Release، تبقى GitHub history هي سجل التنفيذ canonical: commits وPRs وreview threads وCI/checks وmerge history.

تُراجع الوثائق الدائمة ذات current-state claims المتأثرة بحد release أو التغيير مراجعة semantic freshness ضمن عمل release نفسه. هذه المراجعة ليست Phase أو PR أو report مستقلة، ولا يثبتها بحث keyword أو regex وحده؛ يجب قراءة السياق ومقارنته بالـruntime والـmetadata والـlinks والـSHA المعني.

لا تُعرض صياغة branch أو PR أو executor كحالة حالية دائمة، ولا تُعامل نتيجة CI على SHA قديمة كدليل على SHA أحدث.

## 6. بوابة القيمة والاحتفاظ

مجرد حدوث خطوة ليس سببًا لإنشاء Markdown دائم. تكون الملفات التالية non-persistent by default:

- ملف لكل Work Unit أو implementation step؛
- Verification PASS report بلا قيمة مستقلة؛
- Final Review report لمجرد PASS؛
- micro-fix أو remediation logs؛
- status-only docs التي تكرر حالة PR؛
- executor progress logs؛
- phase-completed docs التي لا تضيف معرفة دائمة.

لا يُحتفظ بوثيقة جديدة إلا إذا كانت لها continuing value مستقلة عن Git history، مثل Architecture أو ADR أو How-to أو Package Reference أو operational guide حقيقي. لا تُحذف ADR أو Audit التاريخية ذات القيمة لمجرد قدمها، ولا تُحتفظ بـBlueprint بعد closure إلا إذا كان لها غرض دائم صريح لا يكرره المالك canonical.

## 7. نظافة مراجع نطاق Maatify

الهوية canonical لنطاق Maatify هي:

```text
maatify.dev
```

يُمنع استخدام `maatify.com` أو `www.maatify.com` كهوية أو reference عارٍ، ويُمنع استخدام بريد `@maatify.com`، كما يُمنع استبدال `.dev` بـ`.com` في الأمثلة أو القوالب أو وثائق الحوكمة والحزم.

الاستثناء الوحيد هو absolute URL كاملة ومقصودة لمورد حقيقي مستضاف على `maatify.com`. لا يحول هذا الاستثناء النطاق إلى هوية canonical، ولا يسمح ببريد `.com` أو bare-domain reference، ولا يحمي رابطًا متقادمًا. ويجب فحص السياق الدلالي للرابط؛ البحث النصي وحده ليس إثباتًا للحداثة.
