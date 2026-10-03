# معيار حوكمة القرارات الهندسية الدائمة داخل المستودع

## بيانات المعيار

- **Standard ID:** `std-decision-governance`
- **Standard Version:** `1.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`
- **Version Status:** `OWNER-APPROVED BOOTSTRAP VERSION`
- **Canonical ownership:** `Durable Engineering Decision Recording, Discovery, Applicability, Active-Decision Binding, and Supersession Contract`
- **اللغة المعتمدة:** العربية.
- **النطاق:** حوكمة القرارات الهندسية الدائمة داخل Repository.

## Applicability

تنطبق هذه Standard على كل Repository أو Governance Scope داخل Repository يكون خاضعًا لـ Maatify repository governance ويمكن داخل نطاقه إنشاء أو تطبيق أو مراجعة أو supersede قرارات هندسية دائمة. ويكون هذا التحديد هو الاختبار canonical للانطباق؛ فلا يعتمد على وجود Decision Record حاليًا من عدمه.

تظل Repository التي لا تملك قرارات حالية خاضعة لهذا العقد عند انطباق Governance عليها. وعند الانطباق يجب أن يوجد دائمًا:

```text
docs/decisions/DECISIONS_INDEX.md
```

حتى إذا لم يحتوي الـIndex على أي Decision entries حاليًا. يكون هذا الـIndex هو الـDecision Index canonical الوحيد على مستوى Repository root. وإذا وجدت عدة governed scopes داخل Repository نفسها، تستخدم جميعها نفس Repository-root Index، وتحدد كل Decision في `Scope / Concern` نطاق انطباقها.

لا توسع Profile activation هذه canonical applicability ولا تضيقها؛ تظل Profile composition وآلية Adoption منفصلتين عن اختبار الانطباق المملوك لهذه Standard.

## 1. الملكية والنطاق

تملك هذه Standard ما يلي:

- تحديد متى يتطلب القرار الهندسي Material Decision Record دائمًا.
- تسجيل القرارات الدائمة داخل Repository واكتشافها وفهرستها.
- نموذج حالات القرار، وربط القرارات `ACTIVE` بالتنفيذ الحالي.
- الفحص السابق للتخطيط والتنفيذ والمراجعة.
- إعادة فتح القرار واستبداله وتوثيق سلسلة `Supersession`.

لا تملك هذه Standard:

- Architecture نفسها.
- Business أو Runtime contract نفسها.
- Public API نفسها.
- general role authority.
- Git lifecycle.
- document freshness أو retention أو language.
- Standards Adoption mechanics.

تظل الملكية الحالية لهذه الموضوعات في العقود canonical الخاصة بها. ولا يجعل Decision Record مكتوبًا قرارًا غير مصرح به قرارًا `ACTIVE`.

## 2. Material Decision Threshold

يكون `Material Decision` قرارًا له **continuing effect** يتجاوز micro-step الحالية، ويغلق اختيارًا أو يفرضه أو يمنعه على عمل لاحق. ويشمل ذلك، بحسب الانطباق:

- اختيار Architecture أو architectural boundary.
- Policy.
- Ownership.
- Public Contract.
- security أو data boundary.
- integration boundary.
- persistent cross-cutting convention.
- reusable implementation pattern.
- important workflow rule.
- durable non-goal أو intentional limitation.
- approved exception أو deviation عندما يسمح العقد المالك بذلك.
- قرارًا يمنع أو يفرض اختيارًا في Work Units لاحقة.

لا تحول هذه القاعدة كل implementation detail إلى ADR. ولا يحتاج technical micro-choice إلى Durable Decision Record لمجرد وجوده إذا كان:

- محصورًا في المهمة الحالية؛
- لا يغلق اختيارًا مستقبليًا؛
- لا يغير Architecture أو Policy أو Public Contract أو Ownership؛
- ولا يملك continuing cross-task value.

## 3. Mandatory Durable Persistence

العقد الإلزامي هو:

```text
Approved Material Decision
→ Durable Repository Decision Record
→ Decision Index
```

لا يجوز أن يظل Material Decision المعتمد موجودًا فقط في chat أو Prompt أو PR description أو PR comment أو review comment أو executor report أو audit أو roadmap أو memory أو verbal/external context. قد تثبت هذه المصادر أن القرار نوقش أو اتُخذ، لكنها ليست بديلًا عن Durable Repository Decision Record.

إذا ظهر القرار أثناء Work Unit، يجب تسجيله **before or atomically with the implementation that relies upon it**. ولا يجوز قبول التنفيذ ثم ترك تسجيل القرار كـ optional follow-up.

لا يشترط هذا العقد Commit منفصلة؛ يجوز حفظ القرار والتنفيذ داخل نفس bounded PR عندما تكون topology مناسبة. لكن لا تكتمل Acceptance إذا كان التنفيذ يعتمد على Material Decision لم تُثبت داخل Repository.

## 4. Decision Records

المكان canonical للقرارات المحلية هو:

```text
docs/decisions/
```

مصطلح `Durable Repository Decision Record` هو المصطلح المستخدم داخل Decision Governance للـdurable artifact المطلوبة. لكن كل Material Decision جديدة بعد انطباق هذا العقد **MUST** تكون Decision Record تؤدي canonical ADR role المملوك لـ`DOCUMENTATION_LIFECYCLE_STANDARD_AR.md`. لا ينشئ هذا المعيار document type موازيًا للـADR ولا يفرض filename convention جديدة؛ تظل Stable Decision ID هي الهوية المطلوبة.

ينقسم نطاق الملكية كما يلي:

- **Decision Governance:** يحدد متى تكون الـdurable decision مطلوبة، وStable Decision ID، وStatus Model، وDecision Index، وdiscovery/preflight، وapplicability/binding، وreopen/supersession.
- **Documentation Lifecycle:** يملك ADR document role، وhistorical/current semantics، وdocument lifecycle، وfreshness/retention/language.

الملفان الحاليان `INITIAL_STANDARDS_GOVERNANCE_DECISIONS_AR.md` و`SELECTIVE_STANDARDS_ADOPTION_DECISION_AR.md` يظلان legacy historical decision records كما هما. لا تعيد هذه remediation تسميتهما أو كتابتهما أو تحويلهما شكليًا إلى ADR files أو إضافة metadata لهما، ويجوز للـIndex الإشارة إليهما.

للقرارات الجديدة بعد تطبيق هذه Standard يكون الافتراضي **قرارًا ماديًا واحدًا لكل Record**. ولكل Decision:

- Stable Decision ID فريد ومستقر.
- Title.
- Status.
- Date، عند انطباقها.
- Decision Authority / Deciders.
- Scope / Concern.
- Context.
- Decision.
- Rationale.
- Consequences.
- Supersedes، عند انطباقها.
- Superseded By، عند انطباقها.
- Canonical Contract / Current Owner عندما يكون القرار منفذًا أو ممثلًا حاليًا بواسطة Standard أو Architecture أو artifact canonical آخر.
- alternatives المهمة فقط عندما تكون ضرورية لفهم سبب إغلاق الاختيار.

لا تفرض هذه القائمة headings فارغة للعناصر غير المنطبقة. ولا تكون filename وحدها هوية القرار؛ فالـDecision ID هو الهوية المستقرة.

## 5. Decision Status Model

القيم canonical الوحيدة هي:

```text
PROPOSED
ACTIVE
SUPERSEDED
REJECTED
DEFERRED
```

معانيها:

- **PROPOSED:** اقتراح لم يعتمد، ولا يستخدم كـimplementation authority.
- **ACTIVE:** قرار معتمد وسارٍ على Scope الخاصة به. وهذه هي الحالة التي تغلق مساحة الاختيار الحالي.
- **SUPERSEDED:** قرار كان معتمدًا ثم استبدله قرار أحدث. يبقى Historical Record ولا يستخدم كـcurrent choice.
- **REJECTED:** اقتراح تمت مراجعته ورفضه. لا يستخدم كـimplementation authority ولا يتحول إلى current implementation contract.
- **DEFERRED:** قرار أو اختيار أُجل حسمه. لا يستخدم كـimplementation authority، وإذا احتاجته المهمة تصبح النقطة unresolved/blocking حتى يتم حسمها حسب authority.

ممنوع اختراع statuses بديلة في هذه Standard.

## 6. Decision Index

عند انطباق هذه Standard يجب أن يوجد دائمًا:

```text
docs/decisions/DECISIONS_INDEX.md
```

يوثق الـIndex حالة الاكتشاف الحالية، وليس ADR ولا نسخة من rationale ولا بديلًا عن Decision Record ولا `STANDARDS_MANIFEST.md`. وإذا لم توجد قرارات بعد، يسجل الـIndex ذلك صراحةً بدل عدم وجود Registry.

يتضمن كل Index entry، بحسب الانطباق:

- Decision ID.
- Title.
- Status.
- Scope / Concern.
- Decision Record.
- Canonical Contract / Current Owner.
- Supersedes.
- Superseded By.

يجب أن يثبت الـIndex سلامة الاكتشاف التالية:

- Decision IDs unique.
- كل Durable Decision Record تحت `docs/decisions/` قابلة للاكتشاف من الـIndex.
- يجوز للـlegacy multi-decision record الظهور في أكثر من Index entry.
- كل Index reference تشير إلى record حقيقية.
- Status في الـIndex مطابق لدورة حياة القرار الحالية.
- لا تحتوي أي Supersession chain على cycle.
- تغيير Status أو Supersession يحدث في Decision Record والـIndex داخل نفس bounded change.
- وجود Decision file غير مفهرسة هو Decision Governance gap.
- وجود قرارات `ACTIVE` متعارضة في overlapping Scope هو unresolved governance conflict؛ ولا يختار Agent واحدة من نفسه.

الـIndex current-state discovery surface فقط؛ ولا تجعل محتوى القرار التاريخي current normative contract. تظل المعايير canonical المشار إليها هي المالكة للعقد المعياري الحالي عند انطباقها.

## 7. Mandatory Decision Preflight

ترتيب الفحص قبل التخطيط أو delegation هو:

```text
Task Scope / Concern
↓
Applicable canonical contracts
↓
Decision Index
↓
Applicable ACTIVE Decisions
↓
Current implementation/repository evidence
↓
Plan
```

### Lead

يجب على الـLead أن يحدد Scope / Concern، ويقرأ `DECISIONS_INDEX.md`، ويحدد القرارات `ACTIVE` المنطبقة، ويقرأ Records الخاصة بها، ويراجع canonical current contracts المشار إليها، ثم يبني الحل داخل المساحة التي لم يغلقها قرار قائم. لا يبدأ من preference شخصية ثم يبحث عن justification.

### Executor

قبل تعديل التنفيذ، يتحقق الـExecutor independently من Decision Index والقرارات `ACTIVE` المنطبقة على owned scope ومن القرارات المقفلة المذكورة في Prompt. إذا وجد قرارًا `ACTIVE` منطبقًا لم يذكره الـPrompt، يلتزم به إذا كان متوافقًا مع المهمة. وإذا تعارض مع Prompt أو objective، يتوقف ويعرض contradiction.

### Reviewer / Lead Acceptance

قبل `PASS` يتحقق الـReviewer والـLead من القرارات `ACTIVE` المنطبقة، ومن عدم مخالفة diff لها، ومن عدم إنشاء parallel pattern متعارض، ومن عدم إعادة فتح اختيار مقفول دون formal supersession contract. ولا يعتمد Reviewer على Prompt أو Executor report كدليل على عدم وجود قرار.

## 8. Active-Decision Binding

تسري القاعدة التالية:

```text
Delegated discretion applies only where no applicable ACTIVE
decision has already closed the choice.
```

وجود implementation أو approach أحدث أو أبسط أو أنظف أو أكثر شيوعًا لا يمنح Lead أو Executor أو Reviewer صلاحية إنشاء Pattern ثانية بجانب القرار `ACTIVE`:

```text
ACTIVE Pattern A

Pattern B looks better
≠
permission to introduce Pattern B
```

إما الالتزام بـA أو بدء formal reopen/supersession. لا يوجد silent coexistence لمجرد تجنب تعديل القرار القديم.

إذا قالت Prompt العادية «اختر X أو Y» بينما أغلق قرار `ACTIVE` الاختيار على X، فلا يعتبر الاختيار مفتوحًا. وإذا تعارضت Prompt العادية مع القرار، يتوقف Executor ويعرض contradiction؛ ولا تعتبر Prompt العادية supersession. يمكن لـCurrent Owner instruction أن تبدأ reopen/supersession وفق authority hierarchy، لكن لا تُقبل dependent implementation مكتملة قبل Durable Decision persistence وIndex update.

## 9. Reopen / Supersession Contract

يتم تغيير القرار `ACTIVE` فقط عبر المسار التالي:

```text
Existing ACTIVE Decision
↓
Explicit authority to reopen/change it
↓
New Durable Decision Record
↓
New Decision = ACTIVE
↓
Old Decision = SUPERSEDED
↓
Decision Index updated
↓
Affected current canonical contracts/docs updated
↓
Implementation proceeds/accepts
```

يذكر الـRecord الجديد `Supersedes: DEC-XXX`، ويذكر القرار القديم والـIndex `Superseded By: DEC-YYY`. ممنوع تعديل ADR التاريخية لتبدو كأن القرار الجديد كان القرار الأصلي، وممنوع حذف rationale التاريخية.

## 10. Authority and Canonical Contract Protection

لا تغير Decision Governance hierarchy الموجودة في AI Collaboration:

- Owner-level decisions تظل للـOwner.
- Lead يحسم technical decisions الطبيعية فقط داخل authority القائمة.
- Executor لا يعتمد Policy أو Architecture أو Ownership أو Public Contract من نفسه.
- Reviewer لا ينشئ Decision جديدة كجزء من review finding.
- Decision Record لا توسع authority الخاصة بمن أنشأها.

ولا تصبح deviation compliant لمجرد تسجيلها في Decision Record:

```text
A repository decision cannot make a prohibited deviation compliant.
```

إذا سمح العقد المالك بـException أو Deviation، تتبع lifecycle والauthority المطلوبة لذلك العقد. وإذا أصبحت Decision ممثلة بواسطة canonical Standard أو Architecture document، تظل Decision Record rationale/history، بينما يبقى current normative contract مملوكًا للـcanonical owner.

## 11. Documentation Lifecycle Boundary

تظل `DOCUMENTATION_LIFECYCLE_STANDARD_AR.md` مالكةً لـ:

- ADR document role؛
- current مقابل historical semantics؛
- document freshness؛
- retention؛
- durable documentation language؛
- historical-record preservation.

وتملك هذه Standard فقط ما يتعلق بحوكمة القرارات الدائمة: ما يتطلب Decision Record، وStatus Model، وDecision Index، والاكتشاف والانطباق والربط، وPreflight، وإعادة الفتح وSupersession. لا تعيد هذه Standard نسخ قواعد Documentation Lifecycle التفصيلية.

## 12. Adoption Boundary

هذه Standard Engineering Standard قابلة للاعتماد عبر Profile. أما `docs/decisions/` فهي repository-local وليست جزءًا من upstream Standards Adoption Set، ولا تُنسخ Central Repository Decision Records إلى Consumer Repository لمجرد اعتماد Standards.

تملك كل Repository قراراتها الخاصة. و`STANDARDS_MANIFEST.md` هو Local Resolver Record للStandards، وليس Decision Registry؛ كما أن Decision Index ليست Manifest، ولا تستخدم إحداهما بدل الأخرى.

## 13. Historical Backfill Safety

ممنوع اختراع قرارات قديمة من PR text أو old chat أو memory أو inferred implementation أو old review reports وتسجيلها كأنها Durable Decisions كانت معتمدة تاريخيًا:

```text
No fabricated historical ADR backfill.
```

إذا كان هناك current canonical Standard يمثل behavior حاليًا دون ADR تاريخية، يظل Standard هو current source of truth ولا تُنشأ ADR تاريخية مخترعة لإكمال الشكل. لا يجوز Backfill إلا مع evidence authoritative كافٍ وowner-approved scope مستقل، وليس كجزء تلقائي من هذه Standard.

## 14. سجل تغييرات المعيار

### `1.0.0`

- تثبيت Durable Material Decision persistence وDecision Index canonical.
- تثبيت Status Model وMandatory Lead/Executor/Reviewer preflight.
- تثبيت ACTIVE-decision binding ومنع parallel conflicting pattern.
- تثبيت formal supersession/reopen.
- تثبيت repository-local decision ownership وعدم نسخ قرارات المستودع المركزي ضمن Adoption.
