import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'release' / 'release-manifest.json'


def test_release_manifest_is_staging_only():
    data = json.loads(MANIFEST.read_text())
    assert data['release_channel'] == 'staging'
    assert data['production_switching'] is False
    assert data['production_outputs_enabled'] is False
    assert data['public_publishing_enabled'] is False
    assert all(value is False for value in data['deployment_policy'].values())


def test_release_manifest_artifacts_exist():
    data = json.loads(MANIFEST.read_text())
    groups = ('required_compose_files', 'required_scripts', 'required_docs')
    for group in groups:
        for relative_path in data[group]:
            assert (ROOT / relative_path).exists(), relative_path


def test_release_verifier_preserves_production_guards():
    script = (ROOT / 'scripts' / 'verify-release.sh').read_text()
    assert 'PRODUCTION_SWITCHING=false' in script
    assert 'PRODUCTION_OUTPUTS_ENABLED=false' in script
    assert 'PUBLIC_PUBLISHING_ENABLED=false' in script
    assert 'production_stream_outputs_allowed' not in script
