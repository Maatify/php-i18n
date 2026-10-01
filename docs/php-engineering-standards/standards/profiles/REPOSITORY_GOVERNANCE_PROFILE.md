# Repository Governance Profile

## Profile Metadata

- **Profile ID:** `repository-governance`
- **Profile Version:** `3.0.0`
- **Purpose / Applicability:** إدارة التعاون، دورة Phase، والحوكمة التشغيلية، وحوكمة القرارات الدائمة في Repository تتبع Maatify engineering workflow.
- **Extends:** `None`

## Required Standards

هذه هي الـ Standards المباشرة لهذا Profile:

- [AI Collaboration Workflow](../ai/AI_COLLABORATION_WORKFLOW_AR.md)
- [GitHub Phase Stack Workflow](../GITHUB_PHASE_STACK_WORKFLOW_AR.md)
- [Documentation Lifecycle Standard](../governance/DOCUMENTATION_LIFECYCLE_STANDARD_AR.md)
- [Decision Governance Standard](../governance/DECISION_GOVERNANCE_STANDARD_AR.md)

لا يضم هذا Profile Package أو Module standards.

## Conditional Applicability

لا يقدم هذا Profile أي override مستقل لقابلية انطباق المعايير الهندسية. يحتفظ كل Standard مطلوب بقواعده canonical applicability الخاصة به. لا يوسع Profile تلك القواعد ولا يضيقها. وتظل تفاصيل الأدوار ودورة Phase مملوكة للملفين المشار إليهما.

## Resolved Dependency Behavior

لا يرث هذا Profile Profile آخر، ومعاييره المباشرة هي المعايير الأربعة المدرجة أعلاه دون غيرها. لا توجد composition موروثة هنا. تظل آلية Adoption والحل مملوكة لـ [STANDARDS_ADOPTION_STANDARD_AR.md](../STANDARDS_ADOPTION_STANDARD_AR.md).

## Scope Notes

يجب أن يسجل المشروع Scope صريحًا لكل Activation، مثل `/` أو مسارات الحوكمة التي يشملها العقد. يمكن تفعيله إلى جانب Profiles أخرى في Repository نفسها.

## Precedence Notes

هذا الملف composition manifest فقط. تطبق قواعد adoption من [STANDARDS_ADOPTION_STANDARD_AR.md](../STANDARDS_ADOPTION_STANDARD_AR.md)، وتظل قواعد التعاون ودورة Phase مملوكة للـ Standards المطلوبة.
