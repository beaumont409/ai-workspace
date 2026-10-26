import json
import os
import stat
import tempfile
import unittest
import urllib.error
from unittest import mock
from pathlib import Path
import importlib.util

MODULE = Path(__file__).parents[1] / "generator/providers/qwen_colab.py"
SPEC = importlib.util.spec_from_file_location("qwen_colab", MODULE)
qwen = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(qwen)

GENERATOR_PATH = Path(__file__).parents[1] / "generator/generate.py"
GENERATOR_SPEC = importlib.util.spec_from_file_location("website_builder_generate", GENERATOR_PATH)
generator = importlib.util.module_from_spec(GENERATOR_SPEC)
GENERATOR_SPEC.loader.exec_module(generator)


class FakeResponse:
    status = 200

    def __init__(self, payload, url="https://colab.example.invalid"):
        self.raw = json.dumps(payload).encode()
        self.url = url

    def __enter__(self):
        return self

    def __exit__(self, *_):
        return False

    def read(self, _limit):
        return self.raw

    def geturl(self):
        return self.url


class QwenAdapterTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.token = self.root / "token"
        self.token.write_text("synthetic-test-token")
        self.token.chmod(0o600)
        self.db = self.root / "state" / "jobs.sqlite3"

    def tearDown(self):
        self.temp.cleanup()

    def adapter(self, opener=None, endpoint="https://colab.example.invalid"):
        config = qwen.QwenConfig(endpoint, str(self.token), str(self.db))
        return qwen.QwenColabAdapter(config, opener=opener or (lambda *_a, **_k: None))

    def test_unconfigured_queues_without_network(self):
        def must_not_call(*_args, **_kwargs):
            raise AssertionError("network must not be called")

        adapter = qwen.QwenColabAdapter(qwen.QwenConfig(None, None, str(self.db)), opener=must_not_call)
        job_id, files = adapter.generate_files("brief", "modern")
        self.assertIsNone(files)
        self.assertEqual(adapter.job_status(job_id), "queued")
        self.assertEqual(stat.S_IMODE(self.db.stat().st_mode), 0o600)

    def test_rejects_non_https_endpoint(self):
        with self.assertRaises(qwen.ProviderDisabled):
            qwen.QwenConfig("http://colab.example.invalid", str(self.token), str(self.db))

    def test_rejects_permissive_token_file(self):
        self.token.chmod(0o644)
        with self.assertRaises(qwen.ProviderDisabled):
            self.adapter().config.read_token()

    def test_healthy_authenticated_generation_returns_only_allowed_files(self):
        health = {"status": "ok", "model": qwen.MODEL_ID, "quantization": "4-bit", "device": "Tesla T4"}
        generated = {"files": {"index.html": "<main>review me</main>", "js/main.js": "// output"}}
        responses = iter([FakeResponse(health), FakeResponse(generated)])
        requests = []

        def opener(request, timeout):
            requests.append((request, timeout))
            return next(responses)

        adapter = self.adapter(opener)
        job_id, files = adapter.generate_files("test brief", "electric-blue-3d")
        self.assertEqual(files, generated["files"])
        self.assertEqual(adapter.job_status(job_id), "completed")
        self.assertEqual(len(requests), 2)
        self.assertEqual(requests[0][0].get_header("Authorization"), "Bearer synthetic-test-token")
        request_body = json.loads(requests[1][0].data)
        self.assertEqual(request_body["theme"], "electric-blue-3d")
        self.assertEqual(requests[0][1], 5.0)
        self.assertEqual(requests[1][1], 180.0)

    def test_offline_provider_keeps_job_queued_without_stale_files(self):
        def opener(*_args, **_kwargs):
            raise urllib.error.URLError("synthetic offline")

        adapter = self.adapter(opener)
        job_id, files = adapter.generate_files("brief", "luxury")
        self.assertIsNone(files)
        state = adapter.queue.get(job_id)
        self.assertEqual(state["status"], "queued")
        self.assertEqual(state["error_code"], "provider_unavailable")
        self.assertIsNotNone(state["retry_after"])

    def test_rejects_wrong_runtime_and_unsafe_paths(self):
        with self.assertRaises(qwen.ProviderUnavailable):
            self.adapter(lambda *_a, **_k: FakeResponse({"status": "ok", "model": qwen.MODEL_ID,
                                                           "quantization": "4-bit", "device": "CPU"})).health_check()
        with self.assertRaises(qwen.InvalidProviderResponse):
            qwen.QwenColabAdapter._validate_files({"files": {"../../etc/passwd": "no"}})

    def test_rejects_cross_host_redirect(self):
        adapter = self.adapter(lambda *_a, **_k: FakeResponse(
            {"status": "ok", "model": qwen.MODEL_ID, "quantization": "4-bit", "device": "Tesla T4"},
            "https://elsewhere.example.invalid/redirected"))
        with self.assertRaises(qwen.ProviderUnavailable):
            adapter.health_check()

    def test_generator_keeps_template_default_and_electric_blue_theme(self):
        self.assertEqual(generator.THEMES[-1], "electric-blue-3d")
        self.assertIn("electric-blue-3d", (generator.TEMPLATES_DIR / "js/theme-switcher.js").read_text())
        self.assertIn('[data-theme="electric-blue-3d"]', (generator.TEMPLATES_DIR / "css/themes.css").read_text())

    def test_generator_qwen_mode_writes_queue_metadata_only_when_unconfigured(self):
        with tempfile.TemporaryDirectory(dir=self.root) as output_dir:
            original_projects_dir = generator.PROJECTS_DIR
            generator.PROJECTS_DIR = Path(output_dir)
            try:
                project = generator.generate_project("Synthetic integration smoke", theme="electric-blue-3d")
                with mock.patch.dict(os.environ, {
                    "WEBSITE_BUILDER_QWEN_COLAB_URL": "",
                    "WEBSITE_BUILDER_QWEN_COLAB_TOKEN_FILE": "",
                    "WEBSITE_BUILDER_QWEN_JOB_DB": str(self.root / "builder-jobs.sqlite3"),
                }, clear=False):
                    result = generator.generate_qwen_files("synthetic prompt", "electric-blue-3d", project)
                self.assertEqual(result["status"], "queued")
                self.assertEqual(result["files_written"], [])
                manifest = json.loads((project / "qwen-job.json").read_text())
                self.assertEqual(manifest["review_required"], True)
                self.assertFalse(manifest["code_executed"])
                self.assertFalse((project / "qwen-generated").exists())
                self.assertIn("electric-blue-3d", (project / "index.html").read_text())
            finally:
                generator.PROJECTS_DIR = original_projects_dir


if __name__ == "__main__":
    unittest.main()
