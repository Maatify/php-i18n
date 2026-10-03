# معيار GitHub Phase Stack Workflow

## بيانات المعيار

- **Standard ID:** `std-github-phase-stack-workflow`
- **Standard Version:** `4.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`
- **اللغة المعتمدة:** العربية.
- **الهدف:** تقليل زمن التسليم الكلي عبر Phase Draft أو Execution Batch وDependency-Aware Execution Train، مع الحفاظ على traceability والمراجعة والاختبارات وجودة `main` دون فرض Branch/PR أو تسلسل إداري لا تدعمه dependencies فعلية.

---

# 1. قاعدة الـPhase الأساسية

**كل Phase ذات عمل فعلي (Executed Phase) يجب أن تصل إلى حد تكامل ومراجعة مكتمل، بحيث لا يدخل إلى `main` إلا عمل مكتمل ومراجع ومثبت بالأدلة كوحدة هندسية مفهومة.** قد يكون حد التكامل هذا Phase Draft مستقلة، أو Work Branch واحدة تمثل Execution Batch تضم عدة Phases مترابطة، وفق قرار boundary المبني على dependencies والمخاطر وقابلية المراجعة.

أما الـPhase التي يثبت Baseline Reconciliation أنها `No-op` بالكامل وفق §5، فلا تنشأ لها Phase Draft أو دورة فروع وPRs؛ تغلق تشغيليًا بأدلة ذلك الإثبات دون إسقاط Acceptance Criteria أو اختلاق تغيير.

يستخدم هذا المعيار نموذج:

```text
Dependency-Aware Phase Train
```

بدل فرض:

```text
Strict Sequential Stack
```

ولا يعني السماح بالتوازي أو batching تخفيف أي Quality Gate أو قبول Phase ناقصة.

## 1.1 فصل المفاهيم وقاعدة `Phase ≠ Branch ≠ PR`

يجب عدم استخدام هذه المصطلحات كأنها طبقات متطابقة:

- **Roadmap Phase:** حد تخطيطي وقبول منطقي يحدد ما الذي يجب إنجازه وإثباته. لا تعني تلقائيًا Branch أو PR مستقلة.
- **Execution Batch:** وحدة تسليم تشغيلية قد تضم Phase واحدة أو عدة Roadmap Phases مترابطة عندما تشترك في repository context أو architecture أو dependencies أو الملفات أو إعداد التحقق، أو عندما يكون استمرار نفس المنفذ على السياق الحالي أسرع من إعادة الفهم في جلسات متعددة.
- **Work Branch:** حد Git للعزل والتنفيذ والمراجعة والـrollback. قد تخدم Branch واحدة Execution Batch كاملة، وقد تكون Phase Draft نفسها عندما لا توجد حاجة لحد تجميع إضافي.
- **Commit:** وحدة traceability داخل Work Branch. يجوز إنشاء Commit واضحة لكل Phase أو logical milestone دون تحويل كل واحدة منها إلى Branch أو PR.
- **PR:** حد المراجعة والتكامل والـcurrent-state لكل governed Work Branch منشورة. لا تعني كل Phase أو Work Unit PR مستقلة؛ قد تغطي PR واحدة Execution Batch كاملة وعدة Phases، لكن لا تبقى non-default work branch منشورة بلا PR مرتبطة بالـtarget الصحيح.

القاعدة الصريحة:

```text
Phase ≠ Branch ≠ PR
```

ولا تعني هذه القاعدة أن Branch وPR يجوز فصلهما بعد نشر العمل. الـinvariant المنفصل هو:

```text
Local unpublished work branch
→ may exist without PR

Published non-default governed work branch
→ MUST have an associated PR
```

تُفتح الـPR كجزء من نفس publication handoff إلى الـparent/integration target الصحيح، ولا يستمر governed execution أو delegation على remote branch جديدة قبل وجودها. إذا كانت branch مرتبطة بالفعل بـPR صحيحة قائمة، تُستخدم الـPR نفسها ولا تُنشأ أخرى.

لا تنشأ دورة GitHub مستقلة لمجرد أن Roadmap تحتوي Phase مرقمة. إذا كانت عدة Phases مترابطة وآمنة للتنفيذ المتتابع، يجوز تنفيذها في Execution Batch واحدة وWork Branch واحدة وPR واحدة، مع حفظ phase-level traceability عبر Commits واضحة وتوثيق الـmapping والـevidence.

المبدأ التشغيلي:

```text
Prefer context reuse over artificial parallelism.
```

إذا استطاع نفس المنفذ تنفيذ عدة Phases مترابطة بكفاءة باستخدام فهمه الحالي للمستودع والمعايير والـarchitecture، فلا تقسم المهمة على عدة agents أو sessions لمجرد تحقيق parallelism شكلي.

## 1.2 الهيكل النموذجي

يكون الهيكل المفاهيمي:

```text
main
└── phase-draft أو batch-integration-branch
    ├── execution batch 1 / work branch
    │   ├── phase-a commits
    │   └── phase-b commits
    ├── execution wave 2 (إذا أثبتت استقلالًا حقيقيًا)
    │   ├── work-branch-c
    │   └── work-branch-d
    └── phase closure
        ├── required fixes         (إذا لزم تغيير)
        └── integration gates
```

الـPhase Draft في هذا الرسم حد تكامل ومراجعة اختياري حسب Execution Topology، وليست وعدًا بBranch لكل Phase. يمكن أن تمثل Draft واحدة عدة Phases داخل Batch واحدة، ويمكن أن تكون Work Branch نفسها عندما لا توجد حاجة لBranch تجميع أخرى. الـWave تخطيط تشغيلي اختياري وليست ملفًا دائمًا إلزاميًا. ولا تصبح الـVerification أو Final Review أو Gate Component لمجرد وجودها في هذا الهيكل؛ لا تنشأ لها Branch أو PR إلا إذا نتج عنها تغيير مستودع مستقل ذي معنى.

---

# 2. Phase Draft

## 2.1 اختيار حد التكامل للـPhase أو الـExecution Batch

1. تُفحص أحدث حالة فعلية لـ`main` دائمًا بوصفها مرجع freshness وdivergence. يبدأ Standalone Work من أحدث `main` مصرح بها، ويبدأ Stacked Work من الـauthorized parent أو Integration Boundary الخاصة بالـstack. تحرك `main` لا يغيّر execution parent تلقائيًا؛ ويحتاج Material Divergence إلى Owner-authorized reconciliation. لا يوجد fallback تلقائي إلى `main` أو Automatic Merge. وتحدد Phase Stack الحاجة إلى reconciliation وحدودها، بينما تُحكم طريقة Git الدقيقة وفق صلاحيات AI Collaboration.
2. قبل إنشاء Branch جديدة، يحدد المساعد القائد هل توجد حاجة فعلية إلى Work Branch مستقلة أو Phase Draft منفصلة. استمرار عدة Phases مترابطة على Work Branch واحدة هو الخيار المفضل عندما تكون dependencies والملفات والسياق مشتركة ولا يضيف الفصل عزلًا أو مراجعة أو rollback وضوحًا. إذا أُنشئت Work Branch ثم نُشرت إلى GitHub، يجب أن ترتبط بـPR صحيحة ضمن نفس publication handoff قبل استمرار العمل عليها.
3. إذا احتاجت الـBatch إلى حد تجميع ومراجعة مستقل، تُعيّن Work Branch واحدة كـPhase Draft أو Batch Integration Branch. لا تنشأ Phase Draft إضافية إذا كانت Work Branch الحالية تؤدي هذا الدور بأمان.
4. يمكن أن تستهدف Work Branches المنفصلة Phase Draft عند وجود توازٍ حقيقي أو ownership مستقل. أما عند عدم الحاجة إلى تجميع منفصل، فتكون Work Branch/Batch PR الواحدة هي حد المراجعة النهائي قبل `main`.
5. لا يدخل `main` إلا حد التكامل المعيّن بعد اكتمال جميع Phases وWork Units وGates المطلوبة. وكل GitHub Merge، سواء كان إلى Phase Draft أو Batch Integration Boundary أو `main`، يحتاج Owner authorization صريحة وفق §8.2.

إذا أثبت Baseline Reconciliation أن الـPhase `No-op` بالكامل، فلا تنفذ هذه الخطوات؛ يطبق مسار الإثبات والإغلاق التشغيلي في §5 بدل إنشاء Draft أو Branch أو PR.

## 2.2 Work Units ذات التغيير

1. كل Work Unit أو Component ينتج تغييرًا في المستودع يجب أن يملك حدًا واضحًا للملكية والقبول، لكنه لا يحتاج تلقائيًا إلى Branch أو PR **مستقلة**؛ يجوز أن يستخدم Branch/PR قائمة للـExecution Batch. أما إذا احتاج Work Branch مستقلة ونُشرت إلى GitHub، فتكون لها PR مرتبطة بها إلزاميًا وفق DEC-017.
2. عندما تكون الوحدات مترابطة أو متتابعة أو تشترك في الملفات أو architecture أو verification setup، يجوز تنفيذها على Work Branch واحدة مع Commits واضحة لكل Phase أو logical milestone.
3. عندما تكون الوحدات مستقلة فعليًا ويكون التوازي أسرع بعد احتساب setup وإعادة الفهم والمراجعة وCI والتكامل والتعارضات، يجوز إنشاء Work Branches منفصلة لها؛ وكل Branch منشورة منها تُفتح لها PR مستقلة إلى Phase Draft أو الـparent الصحيح.
4. إذا كانت Phase Draft Branch منفصلة عن Work Branch، تظل نقطة تجميع محمية ولا تضاف إليها Commits عشوائية. وإذا كانت Work Branch الواحدة هي Batch/Phase Draft المعتمدة، يجوز أن تحتوي على Commits التنفيذ المحددة، مع بقاء review وGates واشتراط Owner authorization لكل GitHub Merge.
5. لا يدخل إلى حد التكامل جزء سليم من Work Unit غير مكتملة. إذا تعثرت Work Unit، تطبق قواعد الاستعادة دون تقسيم acceptance الخاصة بها إلى Branch أو Component بديلة لمجرد مواصلة ceremony.
6. لا يحتاج تغيير صغير مثل ملف واحد أو جدول SQL واحد أو Test صغير أو جزء طبيعي من Phase أكبر إلى Branch أو PR مستقلة إذا أمكن ضمه بأمان داخل Branch/PR قائمة لـBatch مترابطة وقابلة للمراجعة. إذا أنشئت له Branch جديدة ونُشرت رغم ذلك، فلا تُستثنى من mandatory PR pairing.

## 2.3 الدمج المنظم مع التوازي

يجوز تنفيذ عدة Work Units بالتوازي، لكن التوازي خيار زمني لا هدف إداري. يستخدم فقط إذا كان صافي زمن التسليم أقل من التنفيذ المتتابع بعد احتساب:

- إعادة قراءة وفهم الـrepository والسياق.
- قراءة المعايير والـarchitecture ذات الصلة.
- Branch setup وbaseline verification.
- review وCI لكل Branch/PR.
- integration وconflict cost وإعادة الاختبارات المتأثرة.

إذا كان نفس المنفذ يستطيع إنهاء الوحدات المترابطة على Work Branch واحدة أسرع، لا تستخدم عدة agents أو sessions لمجرد زيادة عدد المسارات.

عند اختيار التوازي، يظل دمج الوحدات إلى حد التكامل منظمًا:

1. لا تدمج عدة Components إلى Draft بصورة عمياء.
2. عندما يوجد Component مستقل فعليًا، يجب اعتماده واجتياز Component Gate قبل دمجه.
3. عندما تُختار Phase Draft كحد تجميع، تتم عمليات الدمج إليها واحدة تلو الأخرى حتى تظل حالة Draft معروفة بعد كل دمج.
4. قبل دمج Component مبنية على Draft أقدم، يتحقق المساعد القائد من توافقها مع أحدث Draft HEAD، ومن عدم تغير assumptions أو الملفات المشتركة.
5. إذا كانت المزامنة مطلوبة، يحدد Phase Stack الحاجة والـtarget وأثرها على حد التكامل؛ وتُنفذ طريقة Git الدقيقة فقط وفق صلاحيات AI Collaboration، ثم تعاد checks والمراجعة المتأثرة بعد المزامنة.
6. تخضع عمليات Git وقيودها التفصيلية لقواعد AI Collaboration؛ ولا ينشئ هذا المعيار صلاحية عملية محلية من تلقاء نفسه.

عند اكتمال واعتماد Component مستقلة ذات PR، يجوز دمجها إلى Phase Draft إذا كانت Draft جزءًا من topology المختارة وبعد اجتياز Component Gate وOwner authorization الصريحة لذلك الـGitHub Merge. أما الوحدات المتتابعة داخل Work Branch واحدة فتراجع ضمن تلك الـBranch وفق الـGates نفسها، دون إنشاء Component PR لكل وحدة. لا يجوز دمج Component غير مكتملة أو تمرير تعارض لمجرد أن تنفيذها بدأ في Wave سابقة.

## 2.4 PR Current-State وPost-Merge Reconciliation Gate

عندما تستخدم PR كـWork/Component/Phase/Umbrella أو Integration Boundary، يجب أن تحقق current-state/resume contract المملوك لـ`AI_COLLABORATION_WORKFLOW_AR.md`. لا يعيد هذا المعيار نسخ حقول الوصف أو مسؤوليات Lead؛ يحدد فقط موضعها داخل topology ودورة التكامل.

إذا دُمجت Child/Component PR إلى Parent/Umbrella نشطة، فلا يُعامل الـMerge وحده كاكتمال انتقال الحالة. قبل بدء Work Unit متتابعة أو معتمدة تستخدم الـParent الناتجة مصدرًا لها، يجب إغلاق **Post-Merge Reconciliation Gate** التالية:

1. التحقق من أن الـChild دُمجت فعلًا إلى الـtarget الصحيح ومن exact accepted Child HEAD.
2. تثبيت resulting Parent/Integration HEAD الفعلية بعد الدمج.
3. إضافة historical completion milestone جديدة إلى Parent/Umbrella PR تحدد، بالقدر اللازم للاسترجاع: Child PR، accepted Child HEAD، نتيجة Lead review، resulting Parent HEAD، والنطاق الذي أُغلق.
4. تحديث Parent/Umbrella PR body لتصبح snapshot للحالة المجمعة الحالية: ما اكتمل، ما بقي، الـcurrent boundary/HEAD، blockers إن وجدت، والخطوة الصحيحة التالية.
5. تحديث Child PR current-state metadata بحيث لا تبقى تصف نفسها بحالة pre-merge مثل `READY FOR MERGE` بعد اكتمال الدمج.

هذه البوابة لا تفرض تعليقًا مستقلًا لكل micro-step ولا تعيد نسخ تقارير Executor. يُحفظ فقط ما يغير lifecycle أو review أو integration understanding بصورة مادية.

لا توقف هذه البوابة Work Units مستقلة فعلًا كانت تعمل بالتوازي ولا تعتمد على نتيجة الدمج. لكنها تمنع بدء عمل جديد sequential/dependent من Parent غير reconciled أو استخدام Parent stale بوصفها execution source.

### Same-PR Sequential Transition Gate

إذا حملت Work Branch منشورة والـPR المرتبطة بها عدة Work Units أو logical milestones متتابعة، فلا يلزم Merge أو PR مستقلة بين كل وحدة وأخرى، لكن الانتقال نفسه يجب أن يكون موثقًا قبل بدء الوحدة التالية:

1. تُراجع Work Unit المكتملة وتُثبت Acceptance الخاصة بها على exact HEAD التي تمثلها.
2. تسجل PR timeline milestone موجزة تحدد الوحدة/النطاق المكتمل وaccepted HEAD ونتيجة Lead Review بالقدر اللازم للاسترجاع.
3. يُحدّث PR body بحيث تنتقل current-state snapshot من الوحدة المكتملة إلى ما اكتمل إجمالًا، وما هو العمل الحالي/التالي، وأي blocker أو dependency جديدة.
4. إذا نتج عن الوحدة تغيير committed، تحفظ Git history حدًا مفاهيميًا واضحًا لها أو للـlogical milestone؛ لا ينشأ empty commit لمجرد تسجيل PASS أو Gate.

لا تنطبق هذه البوابة على micro-steps داخل Work Unit واحدة، ولا تفرض فصل Work Units مترابطة إلى Branches أو PRs إضافية. هدفها منع ضياع تاريخ الوحدات المتتابعة داخل PR طويلة العمر ومنع الانتقال بينما current-state surface ما زالت تصف الوحدة السابقة.

في Umbrella طويلة العمر يكون الفصل الإلزامي:

```text
Parent/Umbrella PR body
= current aggregate state / resume point

Parent/Umbrella timeline
= append-only completed-child and material lifecycle history
```

ولا ينشأ من ذلك status/history Markdown artifact داخل المستودع؛ تظل document retention/history semantics مملوكة لـ`DOCUMENTATION_LIFECYCLE_STANDARD_AR.md` عندما تنطبق.

---

# 3. Dependency-Aware Execution

## 3.1 Dependency Planning وExecution Waves

قبل التفويض، يفهم المساعد القائد dependencies المؤثرة، والملفات والـownership، والـPublic Contracts أو assumptions المشتركة، وAcceptance Criteria، والـGates اللازمة قبل العمل المعتمد على ناتج آخر. يكون formal dependency graph وWave numbering مشروطين بتعدد الوحدات أو وجود ترتيب أو توازٍ يجعل هذا artifact مفيدًا؛ أما Single Bounded Task أو Coherent Batch فلا تحتاج graph artifact أو Waves.

عندما يُختار التوازي أو يكون ترتيب الوحدات مؤثرًا، يمكن تنفيذ Work Units داخل نفس الـWave بالتوازي إذا أثبت المساعد القائد:

- عدم وجود dependency تنفيذية مباشرة تتطلب الترتيب.
- عدم وجود تعارض متوقع في Public Contract.
- عدم وجود overlap خطير في الملفات أو ownership.
- عدم اعتماد Work Unit على ناتج غير مدمج من أخرى.
- استقلال Acceptance Criteria وحدود الملفات والمسؤولية.

إذا وجدت dependency أو overlap مؤثر، تنفذ الوحدات المعنية sequential على نفس Work Branch أو على Branchs متتابعة عند الحاجة. التوازي ليس إلزاميًا، وتطبق شروط safe parallelism فقط عندما يُختار التوازي. لا تنتقل الوحدة إلى عمل يعتمد على أخرى قبل اجتياز dependencies الفعلية وGates المطلوبة لها، وبعد إثبات أن كلفة التوازي الصافية أقل.

---

# 4. تعريف Work Unit ومكوناتها

## 4.1 Vertical Work Unit

الأصل أن تكون Work Unit وحدة هندسية كاملة ذات معنى، وليست نوع Artifact منفصلًا. عندما يكون ذلك منطقيًا، تشمل Work Unit الخاصة بـFeature أو Gap واحدة:

```text
Runtime
+ tests الخاصة بها
+ التوثيق المتأثر مباشرة
+ verification الخاص بالتغيير
```

يمنع افتراضيًا تقسيم Gap واحدة إلى Runtime PR وTests PR وDocumentation PR وVerification PR إذا كانت كلها تخص التغيير نفسه ويمكن مراجعتها كوحدة واحدة. تبقى هذه العناصر داخل Work Unit وExecution Batch وWork Branch واحدة متى كان ذلك آمنًا.

يجوز الفصل عند وجود سبب هندسي حقيقي، مثل:

- ownership مستقل.
- dependency مستقلة.
- cross-cutting verification.
- Documentation Sweep عامة للـPhase.
- تغيير واسع يحتاج isolation حقيقيًا.

## 4.2 Verification كـGate

Verification نشاط أو Gate، وليست Component افتراضية.

إذا انتهت Verification بنتيجة `PASSED` ولم تنتج تغييرًا في المستودع:

```text
لا Branch
لا Commit
لا PR
```

يسجل المساعد القائد evidence في التقرير أو المكان التشغيلي المناسب. وإذا كشفت Verification عن تغيير، ينفذ داخل Work Unit أو Work Branch المفتوحة إن كانت ما زالت قيد المراجعة، أو داخل Consolidated Required-Fix Component/Batch عند Phase Closure.

لا تنشأ PR فقط لتسجيل أن الاختبارات نجحت.

## 4.3 Final Review كـGate عند انطباقها

Final Review، إذا اختيرت بسبب risk أو Integration Boundary أو remediation، نشاط قبول ومراجعة وليست Component افتراضية. لا تنشأ Separate Final Review لكل conceptual Phase تلقائيًا.

إذا تضمنت المراجعة remediation غيّرت حالة سبق رفضها أو طلب تعديلها، فيجب قبل الدمج إلى **أي Integration Boundary** تنفيذ `Fresh Full Acceptance Review` للحالة النهائية المتراكمة. فحص إصلاح finding وحدها لا يكفي؛ مسؤولية المساعد القائد ومتطلبات هذه المراجعة يملكها [`AI_COLLABORATION_WORKFLOW_AR.md`](ai/AI_COLLABORATION_WORKFLOW_AR.md)، وهذا القسم يحدد موضعها كبوابة تكامل.

إذا لم تنتج Final Review تغييرًا في المستودع، تسجل نتيجتها كـGate evidence فقط ولا تنشئ Branch أو PR مستقلة. وإذا كشفت عن تغييرات، تطبق قواعد Work Unit أو Consolidated Required Fixes داخل الـBatch، ولا تنشأ سلسلة PRs منفصلة لكل ملاحظة صغيرة.

## 4.4 Consolidated Required Fixes

تجمع findings المتوافقة الناتجة من نفس Review Pass داخل **Consolidated Required-Fix Component أو Batch واحدة** متى كان ذلك آمنًا ومتماسكًا، ويفضل إصلاحها على Work Branch/PR القائمة إن كانت الحدود ما زالت واضحة.

لا تجمع مشاكل غير مترابطة إذا جعل ذلك PR غير قابلة للمراجعة، لكن يمنع إنشاء سلسلة:

```text
fix-1
fix-2
fix-3
docs-fix
status-fix
wording-fix
```

لمجرد أن findings اكتشفت منفردة. إذا كان finding يخص Work Unit مفتوحة، يعالج فيها بدل إنشاء Component إضافية.

---

# 5. No-op وBaseline Reconciliation

## 5.1 No-op Component وPhase Prohibition

إذا كانت Acceptance Criteria لمكوّن أو Phase موجودة بالفعل ومثبتة في الـBaseline الحالي:

```text
لا يعاد تنفيذها
لا ينشأ Branch فارغ
لا تنشأ PR Verification شكلية
```

تصنف الحالة بناءً على الأدلة الفعلية كـ`ALREADY IMPLEMENTED / VERIFIED` أو تصنيف أدق مناسب، ولا تنشأ Component لا تضيف تغييرًا أو دليلًا مطلوبًا.

### Phase مثبتة بالكامل كـNo-op

لا تصنف Phase بأنها `No-op` لمجرد وجود Implementation. يجب أن يثبت Baseline Reconciliation أن **جميع Acceptance Criteria الخاصة بالPhase نفسها** هي:

```text
ALREADY IMPLEMENTED + PROVEN
```

على Baseline معتمدة، وألا يوجد في نطاقها:

- repository change.
- missing proof.
- unresolved decision.
- required verification جديدة.
- documentation change.
- contract gap.

عند تحقق هذه الشروط، تعتبر Phase `Execution No-op` مثبتة بالأدلة، ولا ينشأ لها:

```text
Phase Draft
Branch
PR
empty commit
status-only documentation PR
owner merge ceremony
```

لا يجوز استخدام هذا المسار لإسقاط Acceptance Criteria أو تجاوز دليل مطلوب أو تغيير تاريخ المشروع.

## 5.2 Baseline Reconciliation

في المشاريع ذات Roadmap طويلة أو Legacy Implementation أو Extracted Module/Library أو Migration Baseline، تنفذ Baseline Reconciliation مرة واحدة على النطاق المتبقي عندما يكون ذلك أوفر من إعادة Discovery لكل Phase أو Execution Batch.

التصنيفات الممكنة:

```text
ALREADY IMPLEMENTED + PROVEN
IMPLEMENTED BUT MISSING PROOF
PARTIAL / GAP
NOT IMPLEMENTED
BLOCKED BY DECISION
```

هدفها عدم إعادة بناء الموجود، وكشف الـGaps مبكرًا، وإغلاق الـPhases المثبتة كـNo-op بالأدلة دون إنشاء دورة تنفيذ شكلية، وفهم dependencies الواقعية للعمل المتبقي. Baseline Reconciliation Activity تحليلية وليست PR أو طبقة Approval إلزامية بحد ذاتها.

يجوز أن تنفذ Roadmap طويلة عمدًا في عدد قليل من Execution Batches، مع الاحتفاظ بحدود كل Phase وAcceptance Criteria وphase-level traceability عبر Commits واضحة وEvidence/Documentation مرتبطة بها.

إذا أثبتت Reconciliation أن عدة Phases متتابعة كلها `No-op` بالكامل، يجوز إغلاقها تشغيليًا دفعة واحدة في Evidence/Reconciliation Record واحد، بشرط الاحتفاظ بإثبات Acceptance Criteria لكل Phase وعدم إسقاط أي منها أو تغيير تاريخ المشروع كذبًا.

## 5.3 Phase / Roadmap Compaction

داخل Roadmap معتمدة، يجوز للمساعد القائد **اقتراح** Execution Compaction عندما تكون عدة Phases متتابعة موجودة بالفعل جزئيًا أو كليًا، أو شديدة الترابط، أو لا تمثل Boundaries هندسية مستقلة أثناء التنفيذ. ويجوز عندها تنفيذها في Execution Batch واحدة وWork Branch واحدة وPR واحدة إذا كان ذلك يقلل زمن التسليم الكلي ويحافظ على وضوح المراجعة. إذا كان الدمج المقترح يغير Scope أو Phase Boundaries المعتمدة، فلا ينفذه المساعد القائد من نفسه؛ يعرض الأدلة والبدائل والأثر على المالك، ولا يصبح نافذًا إلا بعد اعتماد المالك.

بعد الاعتماد، يمكن تنفيذ الـPhases ذات العمل الفعلي كـExecution Train أو Phase أوسع وفق هذا المعيار. أما الـPhases المثبتة كـNo-op فتظل مسار Evidence-only وفق §5 ولا تتحول إلى Draft أو PR شكلية.

لا يجوز أن يؤدي ذلك إلى:

- حذف Acceptance Criteria.
- إسقاط Quality Gates.
- الادعاء باكتمال شيء غير مثبت.
- تغيير Architecture أو Public Contract أو Scope مؤثر دون اعتماد مالك المشروع.

يبقى الفصل المفاهيمي في الـRoadmap ممكنًا، بينما تصبح Execution Batches وWork Branches أقل وأكثر منطقية. لا يجوز أن تتحول أرقام الـPhases إلى سبب اصطناعي لفتح Branch أوPR أوSession جديدة.

---

# 6. Tiered Verification وCI

## 6.1 Work Unit وExecution Batch Gates

تشغل كل Work Unit أو Execution Batch checks الكافية لاكتشاف Regression المرتبط بها، إضافة إلى Static/General Gates المطلوبة التي تكون تكلفتها معقولة لنطاقها وProfile المشروع. لا يفرض وجود Commit جديدة أو Phase جديدة تكرار Gate كاملة إذا لم يتغير risk أو dependency أو integration surface.

لا تضطر Documentation-only Work Unit صغيرة إلى تكرار Expensive Integration Matrix بلا سبب، إلا إذا أثبت معيار آخر أن هذا Check إلزامي لهذا النوع من التغيير. لا يجوز في المقابل تخطي Check مرتبطة مباشرة بالسلوك أو العقد المتغير.

## 6.2 Full Applicable Gate عند Meaningful Integration Boundary

عند Meaningful Integration Boundary المختارة، مثل Work Batch مهمة أو Phase Draft أو Final Integration، تشغل Full Applicable Verification لكل الـPhases وWork Units الداخلة في ذلك الحد بحسب Profile المشروع ومعايير CI وTesting، وتشمل عند انطباقها:

- Full Test Suite.
- PHPStan أو Static Analysis.
- Latest وLowest Dependencies.
- Real Service/Database Integration.
- System/E2E Regression Protection.
- Composer/Package Checks.
- Workflow Checks.

تظل CI بواباتها مستقرة وFail-Closed وفق `CI_WORKFLOW_STANDARD.md`، ويظل Testing Standard هو المرجع لتغطية السلوك وSystem/E2E. لا تكرر Full Gate بعد كل تعديل صغير إلا إذا بررته مخاطرة أو dependency أو تغيير في integration surface. لا يعتبر حد التكامل جاهزًا لـ`main` قبل نجاح Full Applicable Gate وجميع Gates وReviews الأخرى المنطبقة.

## 6.3 Quality Invariant

هذا التغيير لا يلغي Review أو Testing أو Quality Gates أو Regression Protection أو شرط أن `main` لا يستقبل عملًا ناقصًا أو غير مثبت. ولا يفرض في المقابل Phase Draft أو Component أو **PR مستقلة لكل Work Unit** أو Verification أو Final Review منفصلة عندما لا تبررها Execution Topology أو المخاطر أو حد تكامل ذي معنى. لكن أي governed Work Branch غير افتراضية تُنشر فعليًا إلى GitHub تظل ملزمة بPR مرتبطة بها وفق DEC-017. تبقى الأدلة والمراجعة والتحقق مطلوبة بقدر العقد والـrisk الفعليين.

---

# 7. Standards Freeze أثناء Active Execution Train

عند بدء Phase أو Execution Batch/Train على Standards Snapshot مثبتة:

- تظل Snapshot هي الـBaseline طوال الـTrain.
- لا يفرض تحديث Upstream Standards Refresh فوريًا أو تلقائيًا.
- لا يحدث Refresh أثناء Phase نشطة إلا إذا طلبه المالك صراحة، أو وجد Security/Correctness Blocker مؤثر، أو كان التغيير الجديد مطلوبًا لإكمال Phase بشكل صحيح.
- تنتظر التحديثات غير الضرورية Boundary مناسبة بين Phases أو Trains.

---

# 8. اكتمال الـPhase والدمج إلى `main`

## 8.1 شروط اكتمال الـExecuted Phase ذات العمل الفعلي

لا تعتبر Phase مكتملة إلا بعد:

1. اكتمال Acceptance Criteria لكل Phase داخلة في الـBatch، وكل Work Unit مطلوبة، أو إثبات No-op لها.
2. اجتياز checks وGates المتأثرة والتصحيحات اللازمة ضمن الـWork Unit أو حد التكامل المختار.
3. اكتمال Documentation durable المرتبطة مباشرة بالـAcceptance عند الحاجة أو إثبات عدم الحاجة إليها.
4. اجتياز Full Applicable Gate وRegression Protection عند Meaningful Integration Boundary المختارة، مع حفظ phase-level traceability داخل الـBatch.
5. تنفيذ Direct Lead Acceptance Review لحد التكامل المعيّن. تكون Independent أو Separate Final Review مشروطة بالـrisk ولا تُنشأ لكل conceptual Phase تلقائيًا.
6. عدم وجود Public Contract أو Architecture أو Scope غير معتمد.

تنطبق هذه الشروط على كل Phase ذات عمل فعلي وعلى Execution Batch التي تجمعها. أما الـPhase المثبتة بالكامل كـ`Execution No-op`، فتغلق فقط وفق Evidence شروط §5، ولا تنشئ Draft أو Full Applicable Gate أو PR أو Merge.

## 8.2 الدمج النهائي

1. يمنع إدخال أي Work Unit أو Documentation أو Fix غير مكتملة أو غير مراجعة مباشرة إلى `main`.
2. بعد اكتمال حد التكامل المعيّن للـExecution Batch وكل Gates وReviews المنطبقة، يكون الحد نفسه جاهزًا للدمج.
3. يظل كل GitHub Merge، بما في ذلك Component PR إلى Phase Draft وPhase Draft أو Batch Integration Boundary إلى `main`، مشروطًا بـOwner authorization صريحة. لا توجد Standing Merge Authority للـLead.
لا يشكل دمج حد التكامل إلى `default branch` بحد ذاته `Standards Adoption` أو `Standards Upgrade`. وتظل `Standards Adoption` و`Standards Upgrade` محكومتين حصريًا بـ`STANDARDS_ADOPTION_STANDARD_AR.md`.
4. إذا ضمت Work Branch/PR واحدة عدة Phases، يجوز أن تنتج Squash Commit واحدة إلى `main`، بشرط أن تكون Commits الـBranch وتوثيق الـPR قد حافظا على phase-level traceability لكل Phase وlogical milestone قبل الدمج.
5. كل Merge إلى Parent/Umbrella نشطة يخضع بعده لـPost-Merge Reconciliation Gate في §2.4 قبل بدء عمل sequential/dependent جديد من الـParent الناتجة.

عندما لا توجد Phase Draft منفصلة، يظل أي GitHub Merge من Work Branch/Batch Integration Boundary مشروطًا بـOwner authorization صريحة؛ لا ينشئ ذلك دورة PR مستقلة لكل Phase.

لا يوجد Phase-to-main Merge أو owner merge ceremony للـPhase المثبتة كـ`Execution No-op`، لأنها لا تنشئ Draft أو Commit أو PR أصلًا.

## 8.3 Git History المستهدف

يظل تاريخ `main` نظيفًا ومفاهيميًا، بينما تحفظ الـBranch والـPR والتوثيق traceability التفصيلية. داخل PR نفسها يكون الوصف current-state snapshot قابلة للاستئناف، بينما تحفظ الـtimeline التاريخ التنفيذي material دون تحويل الوصف إلى diary:

```text
Execution Batch 1 — Phase A + Phase B — complete
Execution Batch 2 — Phase C — complete
```

ولا يتحول إلى سجل تفصيلي لكل Work Unit أو Gate داخل Phase.

---

# 9. الصلاحيات والتوافق

- يظل مالك المشروع صاحب القرار النهائي في الهدف، والأولوية، والـArchitecture الجوهرية، وقرارات Public Contract الجوهرية، وتوسيع Scope المؤثر، واعتماد Execution Compaction عندما يغير Phase Boundaries، ودمج Phase Draft إلى `main`، وTag، وRelease، وPublishing.
- بعد اعتماد Scope الـPhase أو Execution Batch من المالك، يجوز للمساعد القائد إدارة دورة التنفيذ داخل النطاق، عندما تكون الأدوات والصلاحيات متاحة، دون الرجوع للمالك عند كل Micro-step، بما يشمل تقسيم Work Units عند الحاجة، وفهم dependencies، واختيار المنفذين، وتشغيل الوحدات المستقلة بالتوازي عندما يثبت أن صافي الزمن أقل، وإعادة استخدام السياق، وفتح وإدارة Branches/PRs اللازمة فقط، ومراجعتها، وطلب Fixes، وإعادة Verification، واعتماد Component مستقل إن وجد.
- لا تمنح هذه الصلاحية للمساعد القائد أو أي Lead Standing Merge Authority؛ **كل GitHub Merge يحتاج Owner authorization صريحة**. ولا يجوز تغيير Architecture أو Policy أو Public Contract جوهري أو توسيع Scope مؤثر أو Tag/Release/Publish من نفسه.
- لا يحصل المنفذ تلقائيًا على Merge Authority لمجرد أن المساعد القائد يملك إدارة الـPhase. يظل Merge إلى `main` للمالك، ويظل تنفيذ المنفذ محصورًا في التكليف المحدد.
- تطبق صلاحيات Git التفصيلية وقواعد Amend وForce Push وStaging من `AI_COLLABORATION_WORKFLOW_AR.md` دون تعارض مع هذا المعيار.

---

# 10. سجل تغييرات المعيار

## `4.0.0`

- تطبيق `DEC-016`: إضافة PR current-state / Same-PR Sequential Transition / Post-Merge Reconciliation Gates داخل topology التنفيذ والتكامل.
- إلزام Child→Parent/Umbrella propagation بعد الدمج بتثبيت resulting parent HEAD، historical completion milestone، وتحديث parent/child current-state metadata قبل بدء sequential/dependent work جديد من الـParent.
- تثبيت فصل PR body كحالة حالية عن GitHub timeline كتاريخ تنفيذي، مع الحفاظ على `Phase ≠ Branch ≠ PR` وعدم فرض micro-PRs أو تغيير Owner-only GitHub Merge authority.
- تطبيق `DEC-017`: تثبيت pairing إلزامية بين أي published non-default governed work branch والـPR الصحيحة، مع إبقاء عدة Work Units قابلة للمشاركة في Branch/PR واحدة وعدم إنشاء Branch/PR للـNo-op.
## `3.0.1`

- توضيح أن Merge إلى default branch لا يعني Standards Adoption؛ تظل آلية Adoption مملوكة حصريًا لـ`STANDARDS_ADOPTION_STANDARD_AR.md`.
- توضيح أن Phase Stack يملك Phase topology وlifecycle وIntegration Boundary، بينما تظل detailed Git-operation permissions مملوكة لـ`AI_COLLABORATION_WORKFLOW_AR.md`.

## `3.0.0`

- تثبيت أحدث حالة فعلية لـ`main` كمرجع freshness وdivergence فقط، مع إبقاء التنفيذ على الـauthorized stack parent أو Integration Boundary وعدم تحويل تحرك `main` إلى execution parent تلقائي.
- اعتماد topology متناسبة مع المخاطر والتبعيات بدل الفروع وPRs الاحتفالية الإلزامية، مع دعم dependency graphs وExecution Waves وFinal Review عند الحاجة الفعلية.
- إلزام Full Applicable Gate عند Meaningful Integration Boundary، واشتراط Owner authorization صريحة لكل GitHub Merge، مع نفي Standing Merge Authority عن الـLead.
- إلزام `Fresh Full Acceptance Review` بعد remediation وقبل أي Integration Boundary ذات معنى.

## `2.2.0`

- ربط أي remediation تغيّر حالة سبق رفضها أو طلب تعديلها بـ`Fresh Full Acceptance Review` قبل الدمج إلى أي Integration Boundary، مع إحالة مسؤولية المراجعة إلى معيار AI Collaboration.

## `2.1.0`

- تثبيت الفصل المفاهيمي `Phase ≠ Branch ≠ PR` وإضافة `Execution Batch` كوحدة تسليم قد تضم عدة Roadmap Phases مترابطة.
- تفضيل إعادة استخدام السياق على التوازي الاصطناعي، وقصر parallel execution على الحالات المستقلة التي يكون فيها صافي زمن التسليم أقل بعد احتساب كلفة الفهم والإعداد والمراجعة وCI والتكامل.
- منع micro-branching وmicro-PRs غير الضرورية، وإبقاء Runtime والاختبارات والتوثيق والـverification المرتبطة مباشرة داخل Batch وWork Branch واحدة متى كان ذلك آمنًا.
- توضيح أن Phase Draft وحد تكامل اختياريان على مستوى الـBatch، مع إبقاء Phase Draft → `main` owner-only وGitHub Squash Merge وStanding Execution Authority وdependency-aware Waves وNo-op وQuality Gates دون تغيير.
- تركيز Full CI عند Work Batch مهمة أو Phase Draft أو Final Integration بدل تكراره بعد كل Commit أو تعديل صغير بلا سبب risk/dependency.

## `2.0.0`

- استبدال Strict Sequential Stack بنموذج Dependency-Aware Phase Train وExecution Waves.
- اعتماد Vertical Work Units، وتحويل Verification وFinal Review إلى Gates ما لم تنتجا تغييرًا مستقلًا.
- اعتماد Consolidated Required Fixes ومنع No-op Components وBaseline Reconciliation وPhase/Roadmap Compaction.
- اعتماد Component Gate وPhase Integration Gate مع الحفاظ على Full Required Verification قبل `main`.
- إضافة Standards Freeze أثناء Active Execution Train.
- تثبيت Standing Execution Authority للمساعد القائد داخل Phase بعد اعتماد Scope، مع إبقاء سلطة الدمج إلى `main` للمالك.

## `1.0.0`

- الإصدار الأول لنظام Phase Stack ودورة فروع الـPhase Draft والـComponents والدمج النهائي.
