from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_dashboard_i18n_is_globally_injected():
    nginx = (ROOT / "services/dashboard-ui/default.conf").read_text(encoding="utf-8")
    assert '<script src="/i18n.js"></script>' in nginx


def test_dashboard_i18n_supports_three_languages_and_rtl():
    source = (ROOT / "services/dashboard-ui/i18n.js").read_text(encoding="utf-8")
    assert "supported=['ar','en','fr']" in source
    assert "lang==='ar'?'rtl':'ltr'" in source
    assert "['ar','العربية']" in source
    assert "['en','English']" in source
    assert "['fr','Français']" in source


def test_dashboard_i18n_preserves_functional_values_and_technical_content():
    source = (ROOT / "services/dashboard-ui/i18n.js").read_text(encoding="utf-8")
    assert "'CODE','PRE'" in source
    assert "node.tagName==='OPTION'" in source
    assert "setAttribute('value',node.value)" in source
    assert "placeholder','title','aria-label" in source


def test_dashboard_i18n_is_local_only():
    source = (ROOT / "services/dashboard-ui/i18n.js").read_text(encoding="utf-8")
    assert "fetch(" not in source
    assert "translate.googleapis" not in source
    assert "api.openai" not in source
    assert "localStorage.setItem(STORAGE_KEY" in source
